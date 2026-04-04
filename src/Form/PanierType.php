<?php

namespace App\Form;

use App\Entity\LieuTouristique;
use App\Entity\Panier;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
            ->add('lieuTouristique', EntityType::class, [
                'class' => LieuTouristique::class,
                'choice_label' => 'nom',
                'label' => 'Lieu Touristique',
                'placeholder' => 'Sélectionner un lieu',
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('typeService', ChoiceType::class, [
                'label' => 'Type de Service',
                'choices' => [
                    'Visite' => 'Visite',
                    'Excursion' => 'Excursion',
                    'Séjour' => 'Séjour',
                    'Activité' => 'Activité',
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
                'label' => 'Nombre d\'adultes',
                'attr' => ['class' => 'input-modern', 'min' => 1],
            ])
            ->add('nbEnfants', IntegerType::class, [
                'label' => 'Nombre d\'enfants',
                'attr' => ['class' => 'input-modern', 'min' => 0],
            ])
            ->add('prixEstime', MoneyType::class, [
                'label' => 'Prix Estimé',
                'currency' => 'TND',
                'divisor' => 1,
                'scale' => 2,
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('statutItem', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'en_attente',
                    'Confirmé' => 'confirmé',
                    'Annulé' => 'annulé',
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
