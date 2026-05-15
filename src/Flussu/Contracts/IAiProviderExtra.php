<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Capability interface: providers that support $LLMextra / tool-use
 * implement IAiProviderExtra in addition to IAiProvider.
 * -------------------------------------------------------*/
namespace Flussu\Contracts;

interface IAiProviderExtra
{
    /**
     * @param string $userText
     * @param array  $extra   normalized $LLMextra payload
     * @return array          [string $textReply, ?array $tokenUsage, array $llmExtra]
     */
    public function chatExtra(string $userText, array $extra): array;
}
