<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Models\CommunicationTemplate;
use Illuminate\Validation\ValidationException;

class CommunicationTemplateRenderer
{
    /** @return array{subject:string,body:string,variables:array<string,string>} */
    public function render(CommunicationTemplate $template, array $variables): array
    {
        $values = [];
        foreach ($template->variables as $name) {
            $value = $variables[$name] ?? data_get($template->fallbacks, $name) ?? TemplateVariableCatalog::SAFE_FALLBACKS[$name] ?? null;
            if ($value === null || $value === '') {
                throw ValidationException::withMessages(['variables.'.$name => 'A value is required for '.$name.'.']);
            }
            $values[$name] = (string) $value;
        }

        $replace = fn (string $text): string => preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn (array $match) => $values[$match[1]] ?? throw ValidationException::withMessages(['variables.'.$match[1] => 'A value is required for '.$match[1].'.']), $text) ?? $text;

        return ['subject' => $replace((string) $template->subject), 'body' => $replace($template->body), 'variables' => $values];
    }

    /** @return list<string> */
    public function variables(string $subject, string $body): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $subject."\n".$body, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
