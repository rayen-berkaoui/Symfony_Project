<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AddToCartType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateDebut', DateTimeType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'attr' => ['class' => 'input-modern'],
                'constraints' => [
                    new Assert\NotBlank(message: 'La date de début est obligatoire'),
                    new Assert\GreaterThanOrEqual('today', message: 'La date de début doit être aujourd\'hui ou plus tard'),
                ],
            ])
            ->add('dateFin', DateTimeType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'attr' => ['class' => 'input-modern'],
                'constraints' => [
                    new Assert\NotBlank(message: 'La date de fin est obligatoire'),
                ],
            ])
            ->add('nbAdultes', IntegerType::class, [
                'label' => 'Nombre d\'adultes',
                'data' => 1,
                'attr' => ['class' => 'input-modern', 'min' => 1, 'max' => 20],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nombre d\'adultes est obligatoire'),
                    new Assert\Positive(message: 'Le nombre d\'adultes doit être positif'),
                    new Assert\LessThanOrEqual(20, message: 'Maximum 20 adultes'),
                ],
            ])
            ->add('nbEnfants', IntegerType::class, [
                'label' => 'Nombre d\'enfants',
                'data' => 0,
                'attr' => ['class' => 'input-modern', 'min' => 0, 'max' => 20],
                'constraints' => [
                    new Assert\PositiveOrZero(message: 'Le nombre d\'enfants ne peut pas être négatif'),
                    new Assert\LessThanOrEqual(20, message: 'Maximum 20 enfants'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
