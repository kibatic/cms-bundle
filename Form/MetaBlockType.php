<?php

namespace Kibatic\CmsBundle\Form;

use Kibatic\CmsBundle\Meta\MetaContent;
use Kibatic\CmsBundle\Meta\MetaValidator;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Metablock: blocks shown together with a layout (Meta\MetaContent). Its content field is the JSON written by the
 * metablock editor (block/_meta_editor.html.twig).
 */
class MetaBlockType extends AbstractBlockType implements BlockTypeInterface
{
    public function __construct(
        private readonly MetaValidator $validator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        // Shared by every language (Block::SHARED_TYPES): saved without language
        $builder->remove('language')->remove('metaOnly');

        $builder->add('content', HiddenType::class, [
            'label' => false,
            'attr' => ['data-kcms-meta-content' => true],
            // Translated in the "validators" domain, like every error of a form
            'invalid_message' => 'kibatic_cms.meta.invalid',
        ]);

        // Saved normalized, if valid (MetaValidator)
        $builder->get('content')->addModelTransformer(new CallbackTransformer(
            fn (?string $content) => $content,
            function (?string $json): string {
                $meta = MetaContent::fromJson($json);

                if (null !== $error = $this->validator->validate($meta)) {
                    throw new TransformationFailedException('Invalid metablock', invalidMessage: 'kibatic_cms.meta.'.$error);
                }

                return $meta->toJson();
            },
        ));
    }

    public function getBlockPrefix(): string
    {
        return 'kibatic_cmsbundle_metablock';
    }

    public static function getBlockTypeName(): string
    {
        return 'meta';
    }
}
