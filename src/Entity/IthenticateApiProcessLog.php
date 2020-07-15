<?php
namespace JAMS\IthenticateBundle\Entity;

use JAMS\IthenticateBundle\Model\IthenticateApiProcessLog as LogModel;

abstract class IthenticateApiProcessLog extends LogModel
{
    public function prePersist()
    {
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    public function preUpdate()
    {
        return $this->updated_at = new \DateTime();
    }
}
