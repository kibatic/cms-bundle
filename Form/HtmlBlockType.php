<?php

namespace Kibatic\CmsBundle\Form;

use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class HtmlBlockType extends AbstractBlockType implements BlockTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('content', TextareaType::class, [
            'label' => 'form.content',
            // An empty block may be created and filled later; the textarea is hidden by the visual editor, the
            // browser could not show why the form is not sent
            'required' => false,
            'attr' => [
                'class' => 'wysiwyg'
            ]
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'kibatic_cmsbundle_htmlblock';
    }

    public static function getBlockTypeName(): string
    {
        return 'html';
    }
}
