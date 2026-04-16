<?php

namespace App\Form;

use App\Entity\Etablissement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EtablissementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom de l\'établissement'])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => ['maxlength' => 200],
            ])
            ->add('adresse', TextType::class)
            ->add('ville', ChoiceType::class, [
                'choices' => [
                    'Tunis' => 'Tunis',
                    'Le Bardo' => 'Le Bardo',
                    'La Marsa' => 'La Marsa',
                    'Carthage' => 'Carthage',
                    'Sidi Bou Saïd' => 'Sidi Bou Saïd',
                    'La Goulette' => 'La Goulette',
                    'Le Kram' => 'Le Kram',
                    'Ariana' => 'Ariana',
                    'La Soukra' => 'La Soukra',
                    'Raoued' => 'Raoued',
                    'Ettadhamen' => 'Ettadhamen',
                    'Kalaat Landlous' => 'Kalaat Landlous',
                    'Ben Arous' => 'Ben Arous',
                    'Hammam Lif' => 'Hammam Lif',
                    'Hammam Chott' => 'Hammam Chott',
                    'Radès' => 'Radès',
                    'Mégrine' => 'Mégrine',
                    'Fouchana' => 'Fouchana',
                    'Mornag' => 'Mornag',
                    'Manouba' => 'Manouba',
                    'Den Den' => 'Den Den',
                    'Douar Hicher' => 'Douar Hicher',
                    'Oued Ellil' => 'Oued Ellil',
                    'Tebourba' => 'Tebourba',
                    'Nabeul' => 'Nabeul',
                    'Hammamet' => 'Hammamet',
                    'Kelibia' => 'Kelibia',
                    'Korba' => 'Korba',
                    'Menzel Temime' => 'Menzel Temime',
                    'Dar Chaabane' => 'Dar Chaabane',
                    'Soliman' => 'Soliman',
                    'Zaghouan' => 'Zaghouan',
                    'Zriba' => 'Zriba',
                    'Bir Mcherga' => 'Bir Mcherga',
                    'El Fahs' => 'El Fahs',
                    'Bizerte' => 'Bizerte',
                    'Menzel Bourguiba' => 'Menzel Bourguiba',
                    'Mateur' => 'Mateur',
                    'Ras Jebel' => 'Ras Jebel',
                    'Sejnane' => 'Sejnane',
                    'Béja' => 'Béja',
                    'Testour' => 'Testour',
                    'Medjez el Bab' => 'Medjez el Bab',
                    'Nefza' => 'Nefza',
                    'Amdoun' => 'Amdoun',
                    'Jendouba' => 'Jendouba',
                    'Tabarka' => 'Tabarka',
                    'Ain Draham' => 'Ain Draham',
                    'Fernana' => 'Fernana',
                    'Ghardimaou' => 'Ghardimaou',
                    'Le Kef' => 'Le Kef',
                    'Tajerouine' => 'Tajerouine',
                    'Kalaat Senan' => 'Kalaat Senan',
                    'Dahmani' => 'Dahmani',
                    'Siliana' => 'Siliana',
                    'Bouarada' => 'Bouarada',
                    'Gaafour' => 'Gaafour',
                    'Makthar' => 'Makthar',
                    'Sousse' => 'Sousse',
                    'Hammam Sousse' => 'Hammam Sousse',
                    'Akouda' => 'Akouda',
                    'Msaken' => 'Msaken',
                    'Kantaoui' => 'Kantaoui',
                    'Monastir' => 'Monastir',
                    'Skanes' => 'Skanes',
                    'Jemmal' => 'Jemmal',
                    'Moknine' => 'Moknine',
                    'Ksar Hellal' => 'Ksar Hellal',
                    'Mahdia' => 'Mahdia',
                    'El Jem' => 'El Jem',
                    'Chebba' => 'Chebba',
                    'Ksour Essef' => 'Ksour Essef',
                    'Sfax' => 'Sfax',
                    'Sakiet Ezzit' => 'Sakiet Ezzit',
                    'Sakiet Eddaier' => 'Sakiet Eddaier',
                    'Mahres' => 'Mahres',
                    'Kerkennah' => 'Kerkennah',
                    'Kairouan' => 'Kairouan',
                    'Haffouz' => 'Haffouz',
                    'Chebika' => 'Chebika',
                    'Sbikha' => 'Sbikha',
                    'Kasserine' => 'Kasserine',
                    'Sbeitla' => 'Sbeitla',
                    'Thala' => 'Thala',
                    'Feriana' => 'Feriana',
                    'Sidi Bouzid' => 'Sidi Bouzid',
                    'Regueb' => 'Regueb',
                    'Meknassi' => 'Meknassi',
                    'Jilma' => 'Jilma',
                    'Gabès' => 'Gabès',
                    'Métouia' => 'Métouia',
                    'Mareth' => 'Mareth',
                    'Ghannouch' => 'Ghannouch',
                    'Médenine' => 'Médenine',
                    'Djerba' => 'Djerba',
                    'Zarzis' => 'Zarzis',
                    'Ben Guerdane' => 'Ben Guerdane',
                    'Tataouine' => 'Tataouine',
                    'Ghomrassen' => 'Ghomrassen',
                    'Bir Lahmar' => 'Bir Lahmar',
                    'Remada' => 'Remada',
                    'Gafsa' => 'Gafsa',
                    'Metlaoui' => 'Metlaoui',
                    'Redeyef' => 'Redeyef',
                    'Moulares' => 'Moulares',
                    'Tozeur' => 'Tozeur',
                    'Nefta' => 'Nefta',
                    'Degache' => 'Degache',
                    'Kébili' => 'Kébili',
                    'Douz' => 'Douz',
                    'Souk Lahad' => 'Souk Lahad',
                ],
                'placeholder' => 'Choisir ville',
            ])
            ->add('telephone', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 22123456'],
            ])
            ->add('email', EmailType::class, ['required' => false])
            ->add('horaires', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Lun-Ven 08:00-18:00'],
            ])
            ->add('gammePrix', ChoiceType::class, [
                'choices' => [
                    '€' => '€',
                    '€€' => '€€',
                    '€€€' => '€€€',
                    '€€€€' => '€€€€',
                    '⭐' => '⭐',
                    '⭐⭐' => '⭐⭐',
                    '⭐⭐⭐' => '⭐⭐⭐',
                    '⭐⭐⭐⭐' => '⭐⭐⭐⭐',
                    '⭐⭐⭐⭐⭐' => '⭐⭐⭐⭐⭐',
                ],
                'required' => false
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Hôtel' => 'hotel',
                    'Restaurant' => 'restaurant',
                    'Café' => 'cafe',
                    'Musée' => 'museum',
                    'Bar' => 'bar',
                    'Loisir' => 'loisir',
                    'Autre' => 'autre',
                ],
                'required' => false
            ])
            ->add('gouvernorat', ChoiceType::class, [
                'mapped' => false,
                'required' => true,
                'placeholder' => 'Choisir gouvernorat',
                'choices' => [
                    'Tunis' => 'Tunis',
                    'Ariana' => 'Ariana',
                    'Ben Arous' => 'Ben Arous',
                    'Manouba' => 'Manouba',
                    'Nabeul' => 'Nabeul',
                    'Zaghouan' => 'Zaghouan',
                    'Bizerte' => 'Bizerte',
                    'Béja' => 'Béja',
                    'Jendouba' => 'Jendouba',
                    'Le Kef' => 'Le Kef',
                    'Siliana' => 'Siliana',
                    'Sousse' => 'Sousse',
                    'Monastir' => 'Monastir',
                    'Mahdia' => 'Mahdia',
                    'Sfax' => 'Sfax',
                    'Kairouan' => 'Kairouan',
                    'Kasserine' => 'Kasserine',
                    'Sidi Bouzid' => 'Sidi Bouzid',
                    'Gabès' => 'Gabès',
                    'Médenine' => 'Médenine',
                    'Tataouine' => 'Tataouine',
                    'Gafsa' => 'Gafsa',
                    'Tozeur' => 'Tozeur',
                    'Kébili' => 'Kébili',
                ],
            ])
            ->add('telephoneIndicatif', ChoiceType::class, [
                'mapped' => false,
                'required' => false,
                'data' => '+216',
                'choices' => [
                    '+216' => '+216',
                    '+33' => '+33',
                    '+212' => '+212',
                    '+213' => '+213',
                ],
            ])
            ->add('horairesMode', ChoiceType::class, [
                'mapped' => false,
                'required' => false,
                'placeholder' => 'Choisir un modèle',
                'choices' => [
                    'Ouvert toute la journée' => '08:00 - 22:00',
                    'Matin uniquement' => '08:00 - 12:00',
                    'Après-midi uniquement' => '14:00 - 19:00',
                    'Service continu' => '10:00 - 00:00',
                    'Personnalisé' => 'custom',
                ],
            ])
            ->add('images', FileType::class, [
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'label' => 'Choisir image(s)',
                'attr' => [
                    'accept' => 'image/*',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Etablissement::class,
        ]);
    }
}
