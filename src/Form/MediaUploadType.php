<?php

namespace App\Form;

use App\Entity\Album;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\AlbumRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\File;

/**
 * Envoi de plusieurs médias d'un coup, rangés dans le même album avec les mêmes tags.
 * Le type de chaque fichier est contrôlé ensuite par MediaUploader (sur le contenu, pas l'extension).
 *
 * Options :
 * - viewer : compte qui envoie ; la liste ne propose que les albums qu'il peut voir
 * - with_classification : false sur la page d'un album (membres) : ni choix d'album, ni tags
 */
class MediaUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('files', FileType::class, [
                'label' => 'Fichiers',
                'multiple' => true,
                'help' => 'Photos (JPEG, PNG, WebP, GIF ; réduites automatiquement à 6000 pixels de côté), musique (MP3, M4A, OGG, WAV, FLAC), vidéos (MP4, WebM, MOV). 256 Mo maximum par fichier, autant de fichiers que vous voulez.',
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp,image/gif,audio/*,video/mp4,video/webm,video/quicktime'],
                'constraints' => [
                    new Count(min: 1, minMessage: 'Choisissez au moins un fichier.'),
                    new All([new File(maxSize: '256M', maxSizeMessage: 'Fichier trop volumineux ({{ size }} {{ suffix }}, maximum {{ limit }} {{ suffix }}).')]),
                ],
            ]);

        if (!$options['with_classification']) {
            return;
        }

        $viewer = $options['viewer'];
        $builder
            ->add('album', EntityType::class, [
                'label' => 'Album',
                'class' => Album::class,
                'required' => false,
                'placeholder' => '— Aucun album —',
                'query_builder' => static fn (EntityRepository $r) => AlbumRepository::restrictToVisible($r->createQueryBuilder('a'), $viewer)
                    ->orderBy('a.title', 'ASC'),
            ])
            ->add('tags', EntityType::class, [
                'label' => 'Tags',
                'class' => Tag::class,
                'multiple' => true,
                'required' => false,
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('t')->orderBy('t.name', 'ASC'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('viewer')
            ->setAllowedTypes('viewer', User::class)
            ->setDefault('with_classification', true)
            ->setAllowedTypes('with_classification', 'bool');
    }
}
