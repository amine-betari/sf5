<?php
/**
 * Created by PhpStorm.
 * User: aminebetari
 * Date: 26/05/20
 * Time: 22:12
 */

namespace App\Message;


class CommentMessage
{
    private $id;
    private $context;

    public function __construct(int $id, array $context = [])
    {
        $this->id = $id;
        $this->context = $context;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}