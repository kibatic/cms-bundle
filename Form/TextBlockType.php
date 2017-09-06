<?php

namespace Kibatic\CmsBundle\Form;

class TextBlockType extends AbstractBlockType implements BlockTypeInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'kibatic_cmsbundle_textblock';
    }

    public static function getBlockTypeName(): string
    {
        return 'text';
    }
}
