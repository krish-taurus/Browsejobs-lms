<?php

declare(strict_types=1);

namespace App\Services\AI;

use RuntimeException;

/**
 * Loads versioned prompt templates from resources/prompts (CLAUDE.md: "Prompts
 * live in resources/prompts as versioned files — never inline strings") and
 * renders {{var}} placeholders.
 */
final class PromptRepository
{
    /**
     * @param  array<string, string>  $vars
     */
    public function render(string $name, int $version, array $vars = []): string
    {
        $path = resource_path("prompts/{$name}.v{$version}.md");

        if (! is_file($path)) {
            throw new RuntimeException("AI prompt not found: {$name} v{$version}.");
        }

        $template = (string) file_get_contents($path);

        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{{'.$key.'}}'] = $value;
        }

        return strtr($template, $replacements);
    }
}
