<?php
namespace Goetas\Serializer\Subscriber;

use Doctrine\Common\Collections\Collection;
use JMS\Serializer\GenericSerializationVisitor;
use Goetas\Serializer\Annotation\LanguageCollection;
use Doctrine\Common\Annotations\Reader;
use Symfony\Component\HttpFoundation\Request;
use JMS\Serializer\XmlSerializationVisitor;
use JMS\Serializer\EventDispatcher\Event;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use Mercurio\SuperDpiLib\SuperDpiLibHotelBundle\Entity\TipologiaFotoDescrizione;
use Doctrine\ORM\Proxy\Proxy;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\Annotation\Groups;
use Metadata\MetadataFactoryInterface;
use JMS\Serializer\Context;
use JMS\Serializer\Metadata\PropertyMetadata;
use Symfony\Component\HttpFoundation\RequestStack;

class LanguageCollectionSubscriber implements EventSubscriberInterface
{

    public static function getSubscribedEvents()
    {
        return array(
            array(
                'event' => 'serializer.post_serialize',
                'method' => 'onPostSerialize'
            )
        );
    }

    /**
     *
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $request;

    /**
     *
     * @var \Doctrine\Common\Annotations\Reader
     */
    protected $reader;


    public function __construct(RequestStack $request, Reader $reader)
    {
        $this->request = $request->getCurrentRequest();
        $this->reader = $reader;
    }
    public function getPropMetadata(\ReflectionProperty $prop)
    {
        $propMetadata = new PropertyMetadata($prop->getDeclaringClass()->getName(), $prop->getName());
        if($groups = $this->reader->getPropertyAnnotation($prop, 'JMS\Serializer\Annotation\Groups')){
            $propMetadata->groups = $groups->groups;
        }
        return $propMetadata;
    }

    protected function visitObject(Event $event, \ReflectionClass $reflection, $locale, SerializationContext $context)
    {
        $object = $event->getObject();
        $visitor = $event->getVisitor();
        $context = $event->getContext();
        $type = $event->getType();

        foreach ($reflection->getProperties() as $prop) {
            $annotation = $this->reader->getPropertyAnnotation($prop, 'Goetas\Serializer\Annotation\LanguageCollection');

            if ($annotation instanceof LanguageCollection) {

                if($context->getExclusionStrategy() && ($propMetadata = $this->getPropMetadata($prop))){
                    if($context->getExclusionStrategy()->shouldSkipProperty($propMetadata, $context)){
                        continue;
                    }
                }

                $prop->setAccessible(true);
                if (method_exists($object, 'get' . $prop->getName())) {
                    $collection = $object->{'get' . $prop->getName()}();
                } else {
                    $collection = $prop->getValue($object);
                }
                if (isset($collection[$locale])) {
                    $value = $collection[$locale];
                } elseif (($pos = strpos($locale, "_"))!==false && ($localeShort = substr($locale, 0, $pos)) && isset($collection[$localeShort])) {
                    $value = $collection[$localeShort];
                } elseif (isset($collection[$annotation->fallback])) {
                    $value = $collection[$annotation->fallback];
                } else {
                    $value = null;
                    if($annotation->any){ //se settato any a true provo a cercare una stringa di traduzione qualsiasi
                        if (is_array($collection)) {
                            $value = reset($collection);
                        } elseif ($collection instanceof Collection) {
                            $value = $collection->first();
                        } elseif ($collection instanceof \Traversable) {
                            foreach ($collection as $value) {
                                break;
                            }
                        }
                    }
                }

                if ($value !== null && $value !== false) {
                    if (is_object($value)) {
                        $type = array(
                            "name" => get_class($value)
                        );
                    } else {
                        $type = array(
                            "name" => gettype($value)
                        );
                    }

                    if ($visitor instanceof XmlSerializationVisitor) {
                        $element = $visitor->getDocument()->createElement($annotation->entry ?  : $prop->getName());

                        $node = $visitor->getCurrentNode();

                        $visitor->setCurrentNode($element);
                        $node->appendChild($element);

                        $visitor->getNavigator()->accept($value, $type, $context);

                        $visitor->revertCurrentNode();
                    } elseif ($visitor instanceof GenericSerializationVisitor) {
                        $visitor->addData($annotation->entry, $visitor->getNavigator()
                            ->accept($value, $type, $context));
                    }
                }
            }
        }
    }

    /**
     *
     * @param Event $event
     * @return void
     */
    public function onPostSerialize(Event $event)
    {
        $object = $event->getObject();

        $reflection = new \ReflectionObject($object);

        $locale = $this->request->getLocale();

        do {
        	$this->visitObject($event, $reflection, $locale, $event->getContext());
        } while($reflection = $reflection->getParentClass());
    }
}