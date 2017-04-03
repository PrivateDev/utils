<?php

namespace PrivateDev\Utils\Form;

use PrivateDev\Utils\Error\Error;
use PrivateDev\Utils\Error\ErrorCodes;
use PrivateDev\Utils\Error\ErrorList;
use PrivateDev\Utils\Error\ErrorListInterface;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormInterface;

/**
 * Class FormErrorDecorator
 * 
 * @package Utils\Form
 */
class FormErrorAdapter extends ErrorList implements ErrorListInterface
{
    /**
     * @param FormInterface $form
     *
     * @return string
     */
    private function collectOriginName(FormInterface $form)
    {
        if ($form->getParent() !== null && $form->getParent()->getName() !== "") {
            return $this->collectOriginName($form->getParent()) . "[{$form->getName()}]";
        } else {
            return $form->getName();
        }
    }

    /**
     * FormErrorDecorator constructor.
     *
     * @param FormErrorIterator $errorIterator
     * @param int               $validationErrorCode
     */
    public function __construct(FormErrorIterator $errorIterator, $validationErrorCode = ErrorCodes::VALIDATION_ERROR)
    {
        for ($i = 0; $i < $errorIterator->count(); $i++) {
                $error = $errorIterator->offsetGet($i);
            $this->add(new Error(
                $error->getMessage(),
                $error->getMessageTemplate(),
                $error->getMessageParameters(),
                $error->getMessagePluralization(),
                ($error->getCause() && $error->getCause()->getCode()) ? $error->getCause()->getCode() : md5($error->getMessageTemplate()),
                $this->collectOriginName($error->getOrigin())
            ));
        }
    }
}