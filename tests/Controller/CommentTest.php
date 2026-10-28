<?php

namespace App\Tests\Controller;

use App\Entity\Album;
use App\Entity\Comment;
use App\Entity\Media;
use App\Entity\ResetPasswordRequest;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\MediaType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CommentTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private Media $media;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->entityManager->createQuery('UPDATE '.Album::class.' a SET a.coverMedia = NULL')->execute();
        foreach ([Comment::class, Media::class, ResetPasswordRequest::class, User::class, Album::class, Tag::class] as $class) {
            $this->entityManager->createQuery('DELETE FROM '.$class)->execute();
        }

        $this->media = (new Media(MediaType::Photo, bin2hex(random_bytes(8)).'.jpg', 'plage.jpg', 'image/jpeg'))->setTitle('Plage');
        $this->entityManager->persist($this->media);
        $this->entityManager->flush();
    }

    public function testMemberPostsAComment(): void
    {
        $this->client->loginUser($this->createUser('mamy@example.com', 'Mamy'));

        $crawler = $this->client->request('GET', $this->mediaUrl());
        self::assertSelectorTextContains('.comments-empty', 'Pas encore');

        $this->client->submit($crawler->selectButton('Envoyer')->form(['comment[content]' => "  Quelle belle journée !\n<script>alert(1)</script>  "]));

        $comment = $this->entityManager->getRepository(Comment::class)->findOneBy([]);
        self::assertNotNull($comment);
        self::assertResponseStatusCodeSame(303);
        self::assertResponseRedirects($this->mediaUrl().'#comment-'.$comment->getId());
        self::assertSame("Quelle belle journée !\n<script>alert(1)</script>", $comment->getContent());
        self::assertSame('mamy@example.com', $comment->getAuthor()?->getEmail());

        $this->client->followRedirect();
        self::assertSelectorTextContains('#comment-'.$comment->getId().' .comment-meta', 'Mamy');
        self::assertSelectorTextContains('#comments-title', '(1)');
        self::assertStringNotContainsString('<script>alert(1)', (string) $this->client->getResponse()->getContent());
    }

    public function testEmptyCommentIsRejected(): void
    {
        $this->client->loginUser($this->createUser('mamy@example.com', 'Mamy'));
        $crawler = $this->client->request('GET', $this->mediaUrl());

        $this->client->submit($crawler->selectButton('Envoyer')->form(['comment[content]' => "   \n  "]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.comment-form', 'Le commentaire est vide.');
        self::assertSame(0, $this->entityManager->getRepository(Comment::class)->count());
    }

    public function testCommentWithoutCsrfTokenIsRejected(): void
    {
        $this->client->loginUser($this->createUser('mamy@example.com', 'Mamy'));

        $this->client->request('POST', $this->mediaUrl(), ['comment' => ['content' => 'Coucou']]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager->getRepository(Comment::class)->count());
    }

    public function testHiddenCommentsAreOnlyShownToAdmins(): void
    {
        $mamy = $this->createUser('mamy@example.com', 'Mamy');
        $this->createComment($mamy, 'Visible');
        $this->createComment($mamy, 'Message masqué')->setIsHidden(true);
        $this->entityManager->flush();

        $this->client->loginUser($this->createUser('papy@example.com', 'Papy'));
        $this->client->request('GET', $this->mediaUrl());
        self::assertSelectorCount(1, '.comment');
        self::assertSelectorTextNotContains('.comment-list', 'Message masqué');

        $this->client->loginUser($this->createUser('papa@example.com', 'Papa', admin: true));
        $this->client->request('GET', $this->mediaUrl());
        self::assertSelectorCount(2, '.comment');
        self::assertSelectorTextContains('.comment-hidden', 'Masqué par l\'administration');
    }

    public function testAuthorCanDeleteOwnCommentButNotOthers(): void
    {
        $mamy = $this->createUser('mamy@example.com', 'Mamy');
        $papy = $this->createUser('papy@example.com', 'Papy');
        $mine = $this->createComment($mamy, 'Le mien');
        $other = $this->createComment($papy, 'Celui de Papy');
        $mineId = $mine->getId();
        $this->client->loginUser($mamy);

        $crawler = $this->client->request('GET', $this->mediaUrl());
        // Un seul bouton « Supprimer » : celui de son propre commentaire
        self::assertCount(1, $crawler->filter('.comment-delete'));
        self::assertCount(1, $crawler->filter('#comment-'.$mineId.' .comment-delete'));

        // Supprimer le commentaire d'un autre, même avec un jeton valide pour son propre commentaire
        $this->client->request('POST', '/comments/'.$other->getId().'/delete', [
            '_token' => $crawler->filter('#comment-'.$mineId.' input[name="_token"]')->attr('value'),
        ]);
        self::assertResponseStatusCodeSame(403);

        $this->client->submit($crawler->filter('#comment-'.$mineId.' .comment-delete')->form());
        self::assertResponseRedirects($this->mediaUrl().'#comments', 303);

        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(Comment::class)->find($mineId));
        self::assertNotNull($this->entityManager->getRepository(Comment::class)->find($other->getId()));
    }

    public function testDeleteRequiresAValidCsrfTokenAndPost(): void
    {
        $mamy = $this->createUser('mamy@example.com', 'Mamy');
        $comment = $this->createComment($mamy, 'Le mien');
        $this->client->loginUser($mamy);

        $this->client->request('POST', '/comments/'.$comment->getId().'/delete', ['_token' => 'faux']);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/comments/'.$comment->getId().'/delete');
        self::assertResponseStatusCodeSame(405);

        self::assertSame(1, $this->entityManager->getRepository(Comment::class)->count());
    }

    public function testAdminCanDeleteAnyComment(): void
    {
        $comment = $this->createComment($this->createUser('mamy@example.com', 'Mamy'), 'Le mien');
        $commentId = $comment->getId();
        $this->client->loginUser($this->createUser('papa@example.com', 'Papa', admin: true));

        $crawler = $this->client->request('GET', $this->mediaUrl());
        $this->client->submit($crawler->filter('#comment-'.$commentId.' .comment-delete')->form());

        self::assertResponseRedirects();
        self::assertSame(0, $this->entityManager->getRepository(Comment::class)->count());
    }

    public function testCommentsFollowTheirMediaAndOutliveTheirAuthor(): void
    {
        $mamy = $this->createUser('mamy@example.com', 'Mamy');
        $this->createComment($mamy, 'Souvenir de Mamy');
        $mamyId = $mamy->getId();

        $this->entityManager->remove($mamy);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->client->loginUser($this->createUser('papy@example.com', 'Papy'));
        $this->client->request('GET', $this->mediaUrl());
        self::assertSelectorTextContains('.comment-meta', 'Ancien membre');
        self::assertNull($this->entityManager->getRepository(User::class)->find($mamyId));

        // Suppression du média : ses commentaires partent avec lui (ON DELETE CASCADE)
        $this->entityManager->createQuery('DELETE FROM '.Media::class)->execute();
        self::assertSame(0, $this->entityManager->getRepository(Comment::class)->count());
    }

    public function testAdminModeratesCommentsInTheBackOffice(): void
    {
        $comment = $this->createComment($this->createUser('mamy@example.com', 'Mamy'), 'À modérer');
        $this->client->loginUser($this->createUser('papa@example.com', 'Papa', admin: true));

        $this->client->request('GET', '/admin/comment');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('td[data-column="content"]', 'À modérer');
        self::assertSelectorTextContains('td[data-column="media"]', 'Plage');
        // Pas de création de commentaire depuis l'administration
        $this->client->request('GET', '/admin/comment/new');
        self::assertResponseStatusCodeSame(403);

        $editUrl = '/admin/comment/'.$comment->getId().'/edit';
        $crawler = $this->client->request('GET', $editUrl);
        self::assertResponseIsSuccessful();
        $this->client->request('POST', $editUrl, [
            'Comment' => [
                'isHidden' => '1',
                // Champs désactivés : une valeur envoyée malgré tout doit être ignorée
                'content' => 'Texte réécrit',
                '_token' => $crawler->filter('input[name="Comment[_token]"]')->attr('value'),
            ],
            'ea' => ['newForm' => ['btn' => 'saveAndReturn']],
        ]);
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $comment = $this->entityManager->getRepository(Comment::class)->find($comment->getId());
        self::assertTrue($comment->isHidden());
        self::assertSame('À modérer', $comment->getContent());
    }

    public function testMemberCannotAccessCommentModeration(): void
    {
        $this->client->loginUser($this->createUser('mamy@example.com', 'Mamy'));

        $this->client->request('GET', '/admin/comment');

        self::assertResponseStatusCodeSame(403);
    }

    private function mediaUrl(): string
    {
        return '/media/'.$this->media->getId();
    }

    private function createComment(User $author, string $content): Comment
    {
        $comment = (new Comment($this->media, $author))->setContent($content);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $comment;
    }

    private function createUser(string $email, string $displayName, bool $admin = false): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setDisplayName($displayName)
            ->setAdmin($admin)
            ->setPassword('inutilisé');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
