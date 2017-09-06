<?php

namespace Kibatic\CmsBundle\Form;

class HtmlBlockType extends AbstractBlockType implements BlockTypeInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'kibatic_cmsbundle_htmlblock';
    }

    public static function getBlockTypeName(): string
    {
        return 'html';
    }
}
