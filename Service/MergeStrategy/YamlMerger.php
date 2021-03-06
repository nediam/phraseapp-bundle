<?php

namespace nediam\PhraseAppBundle\Service\MergeStrategy;

use Symfony\Component\Yaml\Dumper;

class YamlMerger implements MergeInterface
{
    /**
     * @param $format
     *
     * @return bool
     */
    public function canMerge($format)
    {
        return 'yml' === $format;
    }

    /**
     * @param array $content
     *
     * @return string
     */
    public function merge(array $content)
    {
        $yamlTempArray = [];

        foreach ($content as $input) {
            $input = json_decode($input, true);

            $yamlTempArray = array_merge($yamlTempArray, $input);
        }

        ksort($yamlTempArray);

        $dumper = new Dumper();

        return $dumper->dump($yamlTempArray, 5);
    }
}
