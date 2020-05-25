<?php
/**
 * Created by PhpStorm.
 * User: aminebetari
 * Date: 25/05/20
 * Time: 22:38
 */

namespace App;

use App\Entity\Comment;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SpamChecker
{

    private $client;
    private $endpoint;

    public function __construct(HttpClientInterface $client, string $akismetKey)
    {
        $this->client   = $client;
        $this->endpoint = sprintf('https://%s.rest.akismet.com/1.1/comment-check', $akismetKey);
    }

    public function getSpamScore(Comment $comment, array $context): int
    {
        $response = $this->client->request('POST', $this->endpoint, [
            'body' => array_merge($context, [
                'blog' => 'https://guestbook.example.com',
                'comment_type' => 'comment',
                'comment_author' => $comment->getAuthor(),
                'comment_author_email' => $comment->getEmail(),
                'comment_content' => $comment->getText(),
                'comment_date_gmt' => $comment->getCreatedAt()->format('c'),
                'blog_lang' => 'en',
                'blog_charset' => 'UTF-8',
                'is_test' => true,
            ]),
        ]);

        $headers = $response->getHeaders();
        if ('discard' === ($headers['x-akismet-pro-tip'][0] ?? '')) {
            // spam flagrant
            return 2;
        }

        $content = $response->getContent();

        if (isset($headers['x-akismet-debug-help'][0])) {
            throw new \RuntimeException(sprintf('Unable to check for spam: %s(%s).', $content, $headers['x-akismet-debug-help'][0]));
        }
        // 1 spam ; 0 ham (n'est pas un spam
        return 'true' === $content ? 1 : 0;
    }
}