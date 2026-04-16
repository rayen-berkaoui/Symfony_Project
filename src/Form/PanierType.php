<?php

namespace App\Form;

use App\Entity\Panier;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PanierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('serviceId', IntegerType::class, [
                'label' => 'Identifiant du service',
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('typeService', ChoiceType::class, [
                'label' => 'Type de service',
                'choices' => [
                    'Lieu touristique' => 'Voyage',
                    'Restaurant' => 'Restaurant',
                    'Hôtel' => 'Hotel',
                    'Café' => 'Cafe',
                    'Spa' => 'Spa',
                    'Autre lieu' => 'Lieu',
                ],
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('dateDebut', DateTimeType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('dateFin', DateTimeType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('nbAdultes', IntegerType::class, [
                'label' => 'Adultes',
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('nbEnfants', IntegerType::class, [
                'label' => 'Enfants',
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('nbChambres', IntegerType::class, [
                'label' => 'Chambres',
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('prixEstime', MoneyType::class, [
                'label' => 'Prix estimé',
                'currency' => 'TND',
                'divisor' => 1,
                'scale' => 2,
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('statutItem', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'en_attente',
                    'Confirmé' => 'confirme',
                    'Annulé' => 'annul?',
                ],
                'attr' => ['class' => 'input-modern'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Panier::class,
        ]);
    }
}
