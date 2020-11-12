<?php

namespace Goetas\Serializer\Handler;

use JMS\Serializer\Context;
use JMS\Serializer\JsonDeserializationVisitor;
use JMS\Serializer\XmlDeserializationVisitor;
use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\VisitorInterface;
use JMS\Serializer\GraphNavigator;
use JMS\Serializer\XmlSerializationVisitor;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;

class TranslatableStringHandler implements SubscribingHandlerInterface
{
    private $xmlCData;
    private $translator;

    public static function getSubscribingMethods()
    {
        $methods = array();
        foreach (array('json', 'xml', 'yml') as $format) {
            $methods[] = array(
                'type' => 'TranslatableString',
                'format' => $format,
                'direction' => GraphNavigator::DIRECTION_SERIALIZATION,
                'method' => 'serializeString',
            );
        }

        return $methods;
    }
    public function __construct(TranslatorInterface $translator,  $xmlCData = true)
    {
        $this->xmlCData = $xmlCData;
        $this->translator = $translator;
    }

    public function serializeString(VisitorInterface $visitor, $string, array $type, Context $context)
    {
        $translated = $this->translator->trans($string, array(), isset($type['params'][0])?$type['params'][0]:null);
        if ($visitor instanceof XmlSerializationVisitor && false === $this->xmlCData) {
            return $visitor->visitSimpleString($translated, $type, $context);
        }
        return $visitor->visitString($translated, $type, $context);
    }
}

