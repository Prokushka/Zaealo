<?php

declare(strict_types=1);

namespace App\Data;

final readonly class GeneratedProductCopyData
{
    /**
     * @param  list<string>  $infographicFeatures
     */
    public function __construct(
        public string $title,
        public string $description,
        public array $infographicFeatures,
    ) {}

    /**
     * @param  array{title: string, description: string, infographic_features: list<string>}  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            title: $data['title'],
            description: $data['description'],
            infographicFeatures: $data['infographic_features'],
        );
    }

    /**
     * @return array{title: string, description: string, infographic_features: list<string>}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'infographic_features' => $this->infographicFeatures,
        ];
    }
}
