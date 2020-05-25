<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Form\CommentFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\ConferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\CommentRepository;
use Twig\Environment;
use App\Entity\Conference;
use App\SpamChecker;


class ConferenceController extends AbstractController
{
    private $twig;
    private $entityManager;

    public function __construct(Environment $twig, EntityManagerInterface $entityManager)
    {
        $this->twig = $twig;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/conference", name="conference")
     * @Route("/", name="homepage")
     */
    public function index(ConferenceRepository $conferenceRepository)
    {
        return new Response($this->twig->render('conference/index.html.twig', [
            'conferences' => $conferenceRepository->findAll()
        ]));
    }

    /**
     * @Route("/conference/{slug}", name="conference")
     */
    public function show(Request $request, Conference $conference,
                         CommentRepository $commentRepository,
                         ConferenceRepository $conferenceRepository,
                         SpamChecker $spamChecker,
                         string $photoDir)
    {
        $comment = new Comment();
        $form  = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setConference($conference);

            if ($photo = $form['photo']->getData()) {

                $filename = bin2hex(random_bytes(6)).'.'.$photo->guessExtension();
                try {
                        $photo->move($photoDir, $filename);
                } catch (FileException $e) {
            // unable to upload the photo, give up
                }
                $comment->setPhotoFilename($filename);
            }

            $this->entityManager->persist($comment);
            // Gestion des spams
            $context = [
                'user_ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('user-agent'),
                'referrer' => $request->headers->get('referer'),
                'permalink' => $request->getUri(),
            ];
            if (2 === $spamChecker->getSpamScore($comment, $context)) {
                throw new \RuntimeException('Blatant spam, go away!');
            }
            // Gestion des spams

            $this->entityManager->flush();

            return $this->redirectToRoute('conference', ['slug' => $conference->getSlug()]);
        }



        $offset = max(0, $request->query->getInt('offset', 0));
        $paginator = $commentRepository->getCommentPaginator($conference, $offset);

        return new Response($this->twig->render('conference/show.html.twig', [
            'conferences' => $conferenceRepository->findAll(),
            'conference'  => $conference,
            //'comments'  => $commentRepository->findBy(['conference' => $conference], ['createdAt' => 'DESC']),
            'comments'    => $paginator,
            'previous'    => $offset - CommentRepository::PAGINATOR_PER_PAGE,
            'next'        => min(count($paginator), $offset + CommentRepository::PAGINATOR_PER_PAGE),
            'comment_form'        => $form->createView(),
        ]));
    }
}
