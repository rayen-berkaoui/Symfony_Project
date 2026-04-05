<?php

namespace App\Form;

use App\Entity\Post;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;

class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $allowMediaRemove = (bool) ($options['allow_media_remove'] ?? false);

        $builder
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'attr' => [
                    'rows' => 10,
                    'class' => 'form-control',
                ],
            ])
            ->add('hashtags', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Hashtags (séparez par des virgules ou espaces) :',
                'attr' => [
                    'placeholder' => '#sport, #vacances, #tech, #musique, #art…',
                    'class' => 'form-control',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('imageFiles', FileType::class, [
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'empty_data' => [],
                'label' => false,
                'attr' => [
                    'class' => 'tbn-file-input sr-only',
                    'accept' => 'image/jpeg,image/png,image/gif,image/webp',
                ],
                'constraints' => [
                    new Count(['max' => 12]),
                    new All([
                        'constraints' => [
                            new Image([
                                'maxSize' => '5M',
                                'mimeTypes' => [
                                    'image/jpeg',
                                    'image/png',
                                    'image/gif',
                                    'image/webp',
                                ],
                                'mimeTypesMessage' => 'Chaque fichier doit être une image JPEG, PNG, GIF ou WebP (5 Mo max).',
                            ]),
                        ],
                    ]),
                ],
            ])
            ->add('videoFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => false,
                'attr' => [
                    'class' => 'tbn-file-input sr-only',
                    'accept' => 'video/mp4,video/webm,video/quicktime',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '40M',
                        'mimeTypes' => [
                            'video/mp4',
                            'video/webm',
                            'video/quicktime',
                        ],
                        'mimeTypesMessage' => 'Choisissez une vidéo MP4, WebM ou MOV (40 Mo max).',
                    ]),
                ],
            ]);

        if ($allowMediaRemove) {
            $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
                $post = $event->getData();
                $form = $event->getForm();
                if (!$post instanceof Post || null === $post->getId()) {
                    return;
                }
                if ($post->getImages()->isEmpty()) {
                    return;
                }

                $choices = [];
                foreach ($post->getImages() as $img) {
                    $path = $img->getPath();
                    $label = basename(parse_url($path, PHP_URL_PATH) ?: $path);
                    if (strlen($label) > 40) {
                        $label = substr($label, 0, 37).'…';
                    }
                    $id = $img->getId();
                    if (null !== $id) {
                        $choices[sprintf('%s (#%d)', $label, $id)] = $id;
                    }
                }

                if ($choices === []) {
                    return;
                }

                $form->add('removeImageIds', ChoiceType::class, [
                    'mapped' => false,
                    'required' => false,
                    'multiple' => true,
                    'expanded' => true,
                    'choices' => $choices,
                    'label' => 'Supprimer des images existantes',
                    'attr' => [
                        'class' => 'tbn-remove-images-field',
                    ],
                ]);
            });

            $builder
                ->add('removeVideo', CheckboxType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Supprimer la vidéo actuelle (sans en ajouter une nouvelle)',
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
            'allow_media_remove' => false,
        ]);
    }
}
