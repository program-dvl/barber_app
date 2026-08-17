<?php

namespace App\Domain\BusinessConfiguration\Data;

final readonly class ReadinessResult
{
    /**
     * @param  list<array{code:string,message:string,step:string}>  $blockers
     * @param  list<array{code:string,message:string,step:string}>  $improvements
     */
    public function __construct(
        public array $blockers,
        public array $improvements,
        public ?string $nextStep,
        public bool $publishable,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'publishable' => $this->publishable,
            'blockers' => $this->blockers,
            'improvements' => $this->improvements,
            'next_step' => $this->nextStep,
        ];
    }
}
