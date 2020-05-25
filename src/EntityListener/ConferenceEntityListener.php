<?php
/**
 * Created by PhpStorm.
 * User: aminebetari
 * Date: 25/05/20
 * Time: 00:30
 */

namespace App\EntityListener;

use App\Entity\Conference;
use Symfony\Component\String\Slugger\SluggerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;

class ConferenceEntityListener
{
    private $slugger;

    public function __construct(SluggerInterface $slugger)
    {
        $this->slugger = $slugger;
    }

    public function prePersist(Conference $conference, LifecycleEventArgs $event)
    {
        $conference->computeSlug($this->slugger);
    }

    public function preUpdate(Conference $conference, LifecycleEventArgs $event)
    {
        $conference->computeSlug($this->slugger);
    }

}