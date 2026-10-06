<?php

// Usage: php scripts/coverage-gate.php coverage/clover.xml 90
// Fails (exit 1) when line coverage of the clover report is below the threshold.
[$script, $file, $min] = $argv + [null, 'coverage/clover.xml', '90'];

$metrics = simplexml_load_file($file)->project->metrics;
$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$pct = $statements > 0 ? round($covered / $statements * 100, 2) : 0.0;

printf("Line coverage: %d/%d = %.2f%% (gate: %s%%)\n", $covered, $statements, $pct, $min);
exit($pct + 1e-9 >= (float) $min ? 0 : 1);
