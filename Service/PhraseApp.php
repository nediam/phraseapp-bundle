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
     * @var array
     */
    private $tagCache = [];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $catalogues;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var FileMerger
     */
    private $fileMerger;

    /**
     * @var bool
     */
    private $backup = true;

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
        $this->logger            = $logger;
        $this->eventDispatcher   = $eventDispatcher;
        $this->fileMerger        = $fileMerger;
    }

    public function __destruct()
    {
        $this->cleanUp();
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
     * @param boolean $backup
     *
     * @return PhraseApp
     */
    public function setBackup($backup)
    {
        $this->backup = $backup;

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

        $this->logger->notice('Downloading translations for locale "{targetLocale}" from "{sourceLocale}".', [
            'targetLocale' => $targetLocale,
            'sourceLocale' => $sourceLocale
        ]);

        foreach ($this->catalogues as $catalogueName => $catalogueConfig) {
            $tags      = $catalogueConfig['tags'];
            $tempData  = [];
            $extension = $catalogueConfig['output_format'];

            foreach ($tags as $tag) {
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
                continue;
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
        if (array_key_exists($locale, $this->tagCache) && array_key_exists($tag, $this->tagCache[$locale])) {
            return $this->tagCache[$locale][$tag];
        }

        $response = $this->client->request('locale.download', [
            'project_id'  => $this->projectId,
            'id'          => $locale,
            'file_format' => $format,
            'tag'         => $tag,
        ]);

        $content = $response['text']->getContents();

        $this->tagCache[$locale][$tag] = $content;

        return $content;
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

        if (false === $this->backup) {
            $this->translationWriter->disableBackup();
        }

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
        foreach ($this->downloadedLocales as $locale => $files) {
            foreach ($files as $file) {
                if (false === @unlink($file)) {
                    $this->logger->warning(strtr('Could not delete the translation file "{file}".', ['{file}' => $file]));
                }
            }

            unset($this->downloadedLocales[$locale]);
        }
    }
}
