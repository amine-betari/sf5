<?php
/**
 * Created by PhpStorm.
 * User: aminebetari
 * Date: 26/05/20
 * Time: 22:15
 */

namespace App\MessageHandler;

use App\ImageOptimizer;
use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use Psr\Log\LoggerInterface;
// use Symfony\Bridge\Twig\Mime\NotificationEmail;
// use Symfony\Component\Mailer\MailerInterface;
use App\SpamChecker;
use App\Notification\CommentReviewNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class CommentMessageHandler implements MessageHandlerInterface
{
    private $spamChecker;
    private $entityManager;
    private $commentRepository;
    private $bus;
    private $workflow;
    private $notifier;
    private $imageOptimizer;
    private $photoDir;
    private $logger;

    public function __construct(SpamChecker $spamChecker, EntityManagerInterface $entityManager,
                    CommentRepository $commentRepository,
                    MessageBusInterface $bus,
                    WorkflowInterface $commentStateMachine,
                    ImageOptimizer $imageOptimizer,
                    NotifierInterface $notifier,
                    string $photoDir,
                    LoggerInterface $logger = null)
    {
        $this->spamChecker = $spamChecker;
        $this->entityManager = $entityManager;
        $this->commentRepository = $commentRepository;

        $this->bus = $bus;
        $this->workflow = $commentStateMachine;

//        $this->mailer = $mailer;
        $this->imageOptimizer = $imageOptimizer;
//        $this->adminEmail = $adminEmail;
        $this->notifier = $notifier;
        $this->photoDir = $photoDir;
        $this->logger = $logger;
    }

    public function __invoke(CommentMessage $message)
    {
        $comment = $this->commentRepository->find($message->getId());

        if (!$comment) {
            return;
        }

        if ($this->workflow->can($comment, 'accept')) {

            $score = $this->spamChecker->getSpamScore($comment, $message->getContext());
            $transition = 'accept';

            if (2 === $score) {
                $transition = 'reject_spam';
            } elseif ($score === 1) {
                $transition = 'might_be_spam';
            }

            $this->workflow->apply($comment, $transition);
            $this->entityManager->flush();

            $this->bus->dispatch($message);
        } elseif ($this->workflow->can($comment, 'publish') || $this->workflow->can($comment, 'publish_ham')) {
         /*
          $this->mailer->send((new NotificationEmail())
                ->subject('New comment posted')
                ->htmlTemplate('emails/comment_notifications.html.twig')
          //      ->from($this->adminEmail)
                ->to($this->adminEmail)
                ->context(['comment' => $comment])
            );
         */

        //    $this->notifier->send(new CommentReviewNotification($comment), ...$this->notifier->getAdminRecipients());

            $notification = new CommentReviewNotification($comment, $message->getReviewUrl());
            $this->notifier->send($notification, ...$this->notifier->getAdminRecipients());

        } elseif ($this->workflow->can($comment, 'optimize')) {
            if ($comment->getPhotoFilename()) {
                $this->imageOptimizer->resize($this->photoDir.'/'.$comment->getPhotoFilename());
            }
            $this->workflow->apply($comment, 'optimize');
            $this->entityManager->flush();
        } elseif ($this->logger) {
            $this->logger->debug('Dropping comment message', ['comment' => $comment->getId(), 'state' => $comment->getState()]);
        }
    }
}