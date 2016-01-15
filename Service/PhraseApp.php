<?php
/**
 * @author  nediam
 * @date    06.09.2015 22:47
 */

namespace nediam\PhraseAppBundle\Service;

use nediam\PhraseApp\PhraseAppClient;
use nediam\PhraseAppBundle\Events\PhraseappEvents;
use nediam\PhraseAppBundle\Events\PostDownloadEvent;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Translation\TranslationLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Writer\TranslationWriter;

class PhraseApp implements LoggerAwareInterface
{
    /**
     * @var PhraseAppClient
     */
    private $client;

    /**
     * @var TranslationLoader
     */
    private $translationLoader;

    /**
     * @var TranslationWriter
     */
    private $translationWriter;

    /**
     * @var string
     */
    private $projectId;

    /**
     * @var array
     */
    private $locales;

    /**
     * @var string
     */
    private $tmpPath;

    /**
     * @var string
     */
    private $translationsPath;

    /**
     * @var string
     */
    private $outputFormat;

    /**
     * @var array
     */
    private $downloadedLocales = [];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $catalogues;

    /**
     * @var array
     */
    private $nestedCatalogues;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var FileMerger
     */
    private $fileMerger;

    /**
     * @param PhraseAppClient          $client
     * @param TranslationLoader        $translationLoader
     * @param TranslationWriter        $translationWriter
     * @param array                    $config
     * @param LoggerInterface|null     $logger
     * @param EventDispatcherInterface $eventDispatcher
     * @param FileMerger               $fileMerger
     */
    public function __construct(PhraseAppClient $client, TranslationLoader $translationLoader, TranslationWriter $translationWriter, array $config, LoggerInterface $logger = null, EventDispatcherInterface $eventDispatcher, FileMerger $fileMerger)
    {
        $this->client            = $client;
        $this->translationLoader = $translationLoader;
        $this->translationWriter = $translationWriter;
        $this->projectId         = $config['project_id'];
        $this->locales           = $config['locales'];
        $this->outputFormat      = $config['output_format'];
        $this->translationsPath  = $config['translations_path'];
        $this->catalogues        = $config['catalogues'];
        $this->nestedCatalogues  = $config['nested_catalogues'];
        $this->logger            = $logger;
        $this->eventDispatcher   = $eventDispatcher;
        $this->fileMerger        = $fileMerger;
    }

    /**
     * Sets a logger instance on the object.
     *
     * @param LoggerInterface $logger
     *
     * @return null
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Set output format
     *
     * @param string $outputFormat
     *
     * @return PhraseApp
     */
    public function setOutputFormat($outputFormat)
    {
        $this->outputFormat = $outputFormat;

        return $this;
    }

    /**
     * Get Locales
     *
     * @return array
     */
    public function getLocales()
    {
        return $this->locales;
    }

    /**
     * @param string $targetLocale
     *
     * @return string
     */
    protected function downloadLocale($targetLocale)
    {
        $sourceLocale = $this->locales[$targetLocale];

        if (true === array_key_exists($sourceLocale, $this->downloadedLocales)) {
            $this->logger->notice('Copying translations for locale "{targetLocale}" from "{sourceLocale}".', [
                'targetLocale' => $targetLocale,
                'sourceLocale' => $sourceLocale
            ]);

            foreach ($this->catalogues as $catalogueName => $catalogueConfig) {
                $extension = $catalogueConfig['output_format'];

                $tmpFile = sprintf('%s/%s.%s.%s', $this->getTmpPath(), $catalogueName, $targetLocale, $extension);

                // Make copy because operated catalogues must belong to the same locale
                copy($this->downloadedLocales[$sourceLocale][$catalogueName], $tmpFile);
            }

            return $this->downloadedLocales[$sourceLocale];
        }

        $this->logger->notice('Downloading translations for locale "{targetLocale}" from "{sourceLocale}".', [
            'targetLocale' => $targetLocale,
            'sourceLocale' => $sourceLocale
        ]);

        foreach ($this->catalogues as $catalogueName => $catalogueConfig) {
            $tags      = $catalogueConfig['tags'];
            $tempFiles = [];
            $extension = $catalogueConfig['output_format'];
            $path      = $catalogueConfig['path'] ?: $this->translationsPath;

            foreach ($tags as $tag) {
                $tempPath = $this->getTmpPath();

                $this->logger->notice('Downloading catalogue "{catalogueName}" by tag "{tagName}".', [
                    'catalogueName' => $catalogueName,
                    'tagName'       => $tag
                ]);

                $phraseAppMessage = $this->makeDownloadRequest($sourceLocale, 'simple_json', $tag);

                $tempData[$tag] = $phraseAppMessage;
            }

            $postDownloadEvent = new PostDownloadEvent($tempData, $targetLocale, $catalogueName);
            $this->eventDispatcher->dispatch(PhraseappEvents::POST_DOWNLOAD, $postDownloadEvent);

            if ($postDownloadEvent->getFinalFilePath() !== null) {
                // case when listeners manage the creation of file
                $finalFile = $postDownloadEvent->getFinalFilePath();
            } else {
                $tempContent = [];

                foreach($postDownloadEvent->getTempData() as $data) {
                    $tempContent[] = $data;
                }

                $this->logger->notice('Merging files for format {format}', [
                    'format' => $extension
                ]);

                $mergedContent = $this->fileMerger->merge($tempContent, $extension);

                $finalFile = sprintf('%s/%s.%s.%s', $this->getTmpPath(), $catalogueName, $targetLocale, $extension);
                file_put_contents($finalFile, $mergedContent);
            }

            $this->downloadedLocales[$sourceLocale][$catalogueName] = $finalFile;
        }

        return $this->downloadedLocales[$sourceLocale];
    }

    /**
     * @param $locale
     * @param $format
     *
     * @return array|string
     */
    protected function makeDownloadRequest($locale, $format, $tag)
    {
        $response = $this->client->request('locale.download', [
            'project_id'  => $this->projectId,
            'id'          => $locale,
            'file_format' => $format,
            'tag'         => $tag,
        ]);

        return $response['text']->getContents();
    }

    /**
     * @param string $targetLocale
     */
    protected function dumpMessages($targetLocale)
    {
        // load downloaded messages
        $this->logger->notice('Loading downloaded catalogues from "{tmpPath}"', ['tmpPath' => $this->getTmpPath()]);
        $extractedCatalogue = new MessageCatalogue($targetLocale);
        $this->translationLoader->loadMessages($this->getTmpPath(), $extractedCatalogue);

        // Exit if no messages found.
        if (0 === count($extractedCatalogue->getDomains())) {
            $this->logger->warning('No translation found for locale {locale}', ['locale' => $targetLocale]);

            return;
        }

        $this->logger->notice('Writing translation file for locale "{locale}".', ['locale' => $targetLocale]);
        $this->translationWriter->writeTranslations($extractedCatalogue, $this->outputFormat, ['path' => $this->translationsPath]);
    }

    /**
     * @param array $locales
     */
    public function process(array $locales)
    {
        foreach ($locales as $locale)
        {
            $this->downloadLocale($locale);
            $this->dumpMessages($locale);
        }

        $this->cleanUp();
    }

    /**
     * Get TmpPath
     *
     * @return string
     */
    protected function getTmpPath()
    {
        if (null === $this->tmpPath) {
            $this->tmpPath = $this->generateTmpPath();
        }

        return $this->tmpPath;
    }

    /**
     * @return string
     */
    protected function generateTmpPath()
    {
        $tmpPath = sys_get_temp_dir() . '/' . uniqid('translation', false);
        if (!is_dir($tmpPath) && false === @mkdir($tmpPath, 0777, true)) {
            throw new RuntimeException(sprintf('Could not create temporary directory "%s".', $tmpPath));
        }

        return $tmpPath;
    }

    protected function cleanUp()
    {
        $finder = new Finder();
        $files  = $finder->files()->name('*.*.yml')->in($this->getTmpPath());
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (false === @unlink($file)) {
                $this->logger->warning(strtr('Could not delete the translation file "{file}".', ['{file}' => $file]));
            }
        }
    }
}
