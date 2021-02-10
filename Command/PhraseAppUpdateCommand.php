<?php
/**
 * @author  nediam
 * @date    12.09.2015 12:30
 */

namespace nediam\PhraseAppBundle\Command;


use nediam\PhraseAppBundle\Service\PhraseApp;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class PhraseAppUpdateCommand extends Command
{
    private $availableLocales;
    /** @var PhraseApp */
    private $phraseApp;
    /** @var array */
    private $validators = [];
    /** @var array */
    private $locales = [];

	public function setPhraseAppService(PhraseApp $phraseApp)
	{
		$this->phraseApp = $phraseApp;
    }

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
        $this->availableLocales = $this->phraseApp->getLocales();

        $this->phraseApp->setLogger(new ConsoleLogger($output, [
            LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ALERT     => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::CRITICAL  => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ERROR     => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::WARNING   => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::NOTICE    => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO      => OutputInterface::VERBOSITY_VERBOSE,
            LogLevel::DEBUG     => OutputInterface::VERBOSITY_DEBUG,
        ]));

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
