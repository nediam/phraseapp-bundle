<?php
/**
 * @author  nediam
 * @date    12.09.2015 12:30
 */

namespace nediam\PhraseAppBundle\Command;


use nediam\PhraseAppBundle\Service\PhraseApp;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Bundle\FrameworkBundle\Translation\TranslationLoader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Translation\Catalogue\DiffOperation;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Writer\TranslationWriter;

class PhraseAppUpdateCommand extends ContainerAwareCommand
{
    private $availableLocales;
    /** @var TranslationLoader */
    private $loader;
    /** @var TranslationWriter */
    private $writer;
    /** @var PhraseApp */
    private $phraseApp;
    /** @var string */
    private $translationsPath;
    /** @var string */
    private $tmpPath;
    /** @var array */
    private $validators = [];
    /** @var array */
    private $locales = [];

    protected function configure()
    {
        $this->setName('phraseapp:update')->addOption('locale', null, InputOption::VALUE_REQUIRED);

        $this->addOptionValidator('locale', function ($value) {
            if (null === $value) {
                return;
            }
            $this->locales = array_map('trim', explode(',', $value));
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        foreach ($input->getOptions() as $key => $option) {
            if (array_key_exists($key, $this->validators)) {
                call_user_func_array($this->validators[$key], [
                    $input->getOption($key),
                    $input,
                    $output
                ]);
            }
        }
    }

    /**
     * @param string   $name
     * @param callback $validator
     *
     * @throws \Exception
     */
    protected function addOptionValidator($name, $validator)
    {
        if (!is_callable($validator)) {
            throw new \Exception('Validator is not callable');
        }

        $this->validators[$name] = $validator;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container              = $this->getContainer();
        $this->phraseApp        = $container->get('phrase_app.service');
        $this->availableLocales = $this->phraseApp->getLocales();

        $unsupportedLocales = array_diff($this->locales, array_keys($this->availableLocales));
        if (count($unsupportedLocales)) {
            throw new RuntimeException(sprintf('Unsupported locales "%s"', implode(', ', $unsupportedLocales)));
        }
        if (0 === count($this->locales)) {
            $this->locales = array_keys($this->availableLocales);
        }

        // fetch and save translations
        $this->phraseApp->process($this->locales);
    }
}