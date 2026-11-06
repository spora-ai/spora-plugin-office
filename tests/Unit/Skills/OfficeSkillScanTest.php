<?php

declare(strict_types=1);

use Spora\Plugins\Office\OfficePlugin;
use Spora\Skills\SkillScanner;

/**
 * The skills are read by core's scanner, not by the plugin.
 *
 * A frontmatter key core does not know, a body past the size core reads, or a
 * `name` that does not match its directory all become a finding on the skill
 * rather than an error at load time — and the agent reads a skill with a
 * finding in it. The scan is therefore run here, over the same roots the
 * plugin hands core.
 */
function officeScannedSkills(): array
{
    $scanner = new SkillScanner(
        array_map(
            static fn(string $path): array => ['path' => $path, 'source' => 'office'],
            (new OfficePlugin())->skillPaths(),
        ),
    );

    return $scanner->scan();
}

it('ships skills core can scan without a finding', function () {
    $skills = officeScannedSkills();

    expect($skills)->not->toBe([]);

    foreach ($skills as $skill) {
        $findings = array_map(
            static fn(array $finding): string => $finding['code'] . ': ' . $finding['message'],
            $skill->warnings(),
        );

        expect($findings)->toBe([], $skill->name() . ' has findings: ' . implode('; ', $findings));
    }
});

it('ships both halves of the plugin as a skill', function () {
    $names = array_map(
        static fn($skill): string => $skill->name(),
        officeScannedSkills(),
    );

    expect($names)->toContain('office-documents')
        ->and($names)->toContain('office-presentations');
});
