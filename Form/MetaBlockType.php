<?php

namespace Kibatic\CmsBundle\Form;

use Kibatic\CmsBundle\Meta\MetaContent;
use Kibatic\CmsBundle\Repository\BlockRepository;
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
        private readonly BlockRepository $blocks,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('content', HiddenType::class, [
            'label' => false,
            'attr' => ['data-kcms-meta-content' => true],
            // Translated in the "validators" domain, like every error of a form
            'invalid_message' => 'kibatic_cms.meta.invalid',
        ]);

        // Saved normalized; only existing blocks, and no metablock (a metablock contains no other metablock)
        $builder->get('content')->addModelTransformer(new CallbackTransformer(
            fn (?string $content) => $content,
            function (?string $json): string {
                $meta = MetaContent::fromJson($json);
                $types = [];
                foreach ($this->blocks->findBy(['slug' => $meta->slugs()]) as $block) {
                    $types[$block->getSlug()] = $block->getType();
                }

                if ([] === $meta->items) {
                    throw new TransformationFailedException('No block', invalidMessage: 'kibatic_cms.meta.empty');
                }

                foreach ($meta->slugs() as $slug) {
                    if (!isset($types[$slug]) || self::getBlockTypeName() === $types[$slug]) {
                        throw new TransformationFailedException('Unknown block', invalidMessage: 'kibatic_cms.meta.invalid');
                    }
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
