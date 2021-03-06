<?php

namespace QEEP\QEEPApiClient\V2\Model;

use JMS\Serializer\Annotation as JMS;

class Option
{
    /** @JMS\Type("integer") */
    protected $id;

    /** @JMS\Type("string") */
    protected $feature;

    /** @JMS\Type("integer") */
    protected $position;

    /** @JMS\Type("string") */
    protected $value;

    /** @JMS\Type("boolean") */
    protected $filterable = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): Option
    {
        $this->id = $id;

        return $this;
    }

    public function getFeature(): ?string
    {
        return $this->feature;
    }

    public function setFeature(?string $feature): Option
    {
        $this->feature = $feature;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): Option
    {
        $this->position = $position;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): Option
    {
        $this->value = $value;

        return $this;
    }

    public function getFilterable(): bool
    {
        return $this->filterable;
    }

    public function setFilterable(bool $filterable): Option
    {
        $this->filterable = $filterable;

        return $this;
    }
}
