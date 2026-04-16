<?php

namespace App\Form;

use App\Entity\Panier;
use App\Entity\Reservation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('panier', EntityType::class, [
                'class' => Panier::class,
                'choice_label' => fn (Panier $panier) => sprintf('Panier #%d - %s', (int) $panier->getId(), $panier->getServiceTypeLabel()),
                'label' => 'Panier associé',
                'placeholder' => 'Sélectionner un panier',
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('datePaiement', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('modePaiement', ChoiceType::class, [
                'label' => 'Mode de paiement',
                'choices' => [
                    'Carte bancaire / Paymee' => 'Paymee',
                    'Espèces' => 'Especes',
                ],
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('statutPaiement', ChoiceType::class, [
                'label' => 'Statut du paiement',
                'choices' => [
                    'En attente' => 'En attente',
                    'En cours de paiement' => 'En cours de paiement',
                    'Payé' => 'Paye',
                    'Remboursé' => 'Rembourse',
                ],
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('montantTotal', MoneyType::class, [
                'label' => 'Montant total',
                'currency' => 'TND',
                'divisor' => 1,
                'scale' => 2,
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('codeConfirmation', TextType::class, [
                'label' => 'Code de confirmation',
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('rating', IntegerType::class, [
                'label' => 'Note (1-5)',
                'required' => false,
                'attr' => ['class' => 'input-modern', 'min' => 1, 'max' => 5],
            ])
            ->add('reviewComment', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'attr' => ['class' => 'input-modern', 'rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}
