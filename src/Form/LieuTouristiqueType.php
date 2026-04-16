<?php

namespace App\Form;
use App\Entity\Adresse;
use App\Entity\Categorie;
use App\Entity\LieuTouristique;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class LieuTouristiqueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du lieu',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('prix', MoneyType::class, [
                'label' => 'Prix',
                'required' => false,
                'currency' => 'TND',
                'divisor' => 1,
                'scale' => 2,
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => 'image/*',
                    'class' => 'block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gold-500 file:text-black hover:file:bg-gold-600',
                ],
                'constraints' => [
                    new Image(maxSize: '5M'),
                ],
            ])
            ->add('statut', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
            ])
            ->add('categorie', EntityType::class, [
                'class' => Categorie::class,
                'choice_label' => 'nomCategorie',
                'placeholder' => 'Choisir une categorie',
            ])
            ->add('adresse', EntityType::class, [
                'class' => Adresse::class,
                'choice_label' => static fn (Adresse $adresse): string => (string) $adresse,
                'placeholder' => 'Choisir une adresse',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LieuTouristique::class,
        ]);
    }
}
