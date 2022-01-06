<?php
/**
 * Author: grzegorz
 * Date: 15.01.16 11:29
 */

namespace nediam\PhraseAppBundle\Service;

use nediam\PhraseAppBundle\Service\MergeStrategy\MergeInterface;
use nediam\PhraseAppBundle\Service\MergeStrategy\YamlMerger;

class FileMerger implements FileMergerInterface
{
    /**
     * @var MergeInterface[]
     */
    private $handlers = [];

    /**
     * FileMerger constructor.
     */
    public function __construct()
    {
        $this->handlers[] = new YamlMerger();
    }

    /**
     * @param $content
     * @param $format
     *
     * @return string
     */
    public function merge($content, $format)
    {
        foreach ($this->handlers as $handler) {
            if ($handler->canMerge($format)) {
                return $handler->merge($content);
            }
        }

        throw new \InvalidArgumentException('No handler for requested format');
    }
}
