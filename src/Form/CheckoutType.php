<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CheckoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('modePaiement', ChoiceType::class, [
            'label' => 'Mode de paiement',
            'choices' => [
                'Carte bancaire / Paymee' => 'Paymee',
                'Paiement en espèces' => 'Especes',
            ],
            'expanded' => true,
            'constraints' => [
                new Assert\NotBlank(message: 'Veuillez sélectionner un mode de paiement'),
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
