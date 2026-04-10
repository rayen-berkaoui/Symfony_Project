<?php

namespace App\Form;

use App\Entity\Activite;
use App\Entity\Etablissement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActiviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomActivite', TextType::class, ['label' => 'Nom de l\'activité'])
            ->add('description', TextareaType::class, ['required' => false])
            ->add('categorie', ChoiceType::class, [
                'choices' => [
                    'Sport' => 'sport',
                    'Culture / Art' => 'culture',
                    'Bien-être / Spa' => 'bienetre',
                    'Aventure / Nature' => 'aventure',
                    'Gastronomie' => 'gastronomie',
                    'Autre' => 'autre'
                ],
                'required' => false
            ])
            ->add('duree', IntegerType::class, ['required' => false, 'label' => 'Durée (en minutes)'])
            ->add('niveau', ChoiceType::class, [
                'choices' => [
                    'Débutant' => 'débutant',
                    'Intermédiaire' => 'intermédiaire',
                    'Avancé' => 'avancé'
                ],
                'required' => false
            ])
            ->add('prix', NumberType::class, ['scale' => 2, 'required' => false])
            ->add('devise', TextType::class, ['required' => false, 'data' => 'TND'])
            ->add('dateDebut', DateTimeType::class, ['widget' => 'single_text', 'required' => false])
            ->add('dateFin', DateTimeType::class, ['widget' => 'single_text', 'required' => false])
            ->add('nbPlaces', IntegerType::class, ['required' => false])
            ->add('placesDispo', IntegerType::class, ['required' => false])
            ->add('adresseDepart', TextType::class, ['required' => false])
            ->add('ageMin', IntegerType::class, ['required' => false])
            ->add('equipementInclus', TextType::class, [
                'required' => false,
                'attr' => ['class' => 'equipement-mots-cles', 'id' => 'equipement_inclus_input']
            ])
            ->add('conditionsAnnulation', ChoiceType::class, [
                'choices' => [
                    'Gratuite (24h avant)' => 'Gratuite (24h avant)',
                    'Modérée (5 jours avant)' => 'Modérée (5 jours avant)',
                    'Stricte (7 jours avant)' => 'Stricte (7 jours avant)',
                    'Non remboursable' => 'Non remboursable'
                ],
                'required' => false
            ])
            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'Disponible' => 'disponible',
                    'Complète' => 'complete',
                    'Annulée' => 'annulee'
                ],
                'required' => false
            ])
            ->add('etablissement', EntityType::class, [
                'class' => Etablissement::class,
                'choice_label' => 'nom',
                'label' => 'Établissement lié'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Activite::class,
        ]);
    }
}
