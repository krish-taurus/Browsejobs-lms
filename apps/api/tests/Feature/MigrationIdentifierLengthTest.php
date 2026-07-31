<?php

declare(strict_types=1);

/**
 * MySQL rejects any identifier longer than 64 characters. SQLite does not,
 * and the suite runs on SQLite — so a migration with an over-long generated
 * index or foreign-key name passes every local check and every CI run, then
 * fails on the production database mid-deploy, after earlier statements have
 * already applied. MySQL DDL is not transactional, so that leaves an
 * orphaned table behind.
 *
 * That is exactly what happened to the employer module. This test closes the
 * hole statically: it reproduces Laravel's default naming and fails before
 * anything reaches a real database.
 *
 * When this fails, pass an explicit short name as the second argument to
 * `index()`/`unique()`, or declare the key with `foreign($col, 'short_fk')`.
 */
const MYSQL_MAX_IDENTIFIER = 64;

/**
 * Laravel names an index `<table>_<col>_<col>_<type>` and a foreign key
 * `<table>_<col>_foreign` unless given an explicit name.
 *
 * @return list<array{name: string, file: string}>
 */
function generatedIdentifiers(string $path): array
{
    $source = (string) file_get_contents($path);
    $file = basename($path);
    $found = [];

    preg_match_all("/Schema::(?:create|table)\('([a-z_]+)'/", $source, $tables, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

    foreach ($tables as $i => $match) {
        $table = $match[1][0];
        $start = $match[0][1] + strlen($match[0][0]);
        $end = isset($tables[$i + 1]) ? $tables[$i + 1][0][1] : strlen($source);
        $body = substr($source, $start, $end - $start);

        // ->index([...]) / ->unique([...]) with no explicit name argument.
        preg_match_all('/->(index|unique)\(\[([^\]]*)\]\)/', $body, $keys, PREG_SET_ORDER);
        foreach ($keys as $key) {
            $columns = array_filter(array_map(
                static fn (string $c): string => trim($c, " \t\n'\""),
                explode(',', $key[2]),
            ));
            $found[] = [
                'name' => $table.'_'.implode('_', $columns).'_'.$key[1],
                'file' => $file,
            ];
        }

        // ->foreignId('col') without an explicit constraint name.
        preg_match_all("/->foreignId\('([a-z_]+)'\)/", $body, $fks, PREG_SET_ORDER);
        foreach ($fks as $fk) {
            $found[] = ['name' => $table.'_'.$fk[1].'_foreign', 'file' => $file];
        }
    }

    return $found;
}

it('never generates an identifier MySQL will reject', function (): void {
    $files = glob(database_path('migrations/*.php')) ?: [];

    expect($files)->not->toBeEmpty();

    $offenders = [];
    foreach ($files as $file) {
        foreach (generatedIdentifiers($file) as $identifier) {
            if (strlen($identifier['name']) > MYSQL_MAX_IDENTIFIER) {
                $offenders[] = sprintf(
                    '%s (%d chars) in %s',
                    $identifier['name'],
                    strlen($identifier['name']),
                    $identifier['file'],
                );
            }
        }
    }

    expect($offenders)->toBe([], implode("\n", $offenders));
});
