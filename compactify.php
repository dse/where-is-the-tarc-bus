<?php
function compactify_slurp(&$string, $regex, &$matches) {
    if (preg_match($regex, $string, $matches, PREG_OFFSET_CAPTURE)) {
        $string = substr($string, strlen($matches[0][0]));
        return true;
    }
    return false;
}
function compactify($json) {
    $result = '';
    $blah = $json;
    $cursor = 0;
    $indent = 0;
    $levels = [];

    $simplified = '';
    $regular = '';
    $values = 0;

    while (1) {
        if (compactify_slurp($blah, '/^\s*\{\s*/s', $matches)) {
            $levels[] = [ 'indent' => $indent ];
            $cursor += 2;
            $indent = $cursor;
            if (isset($simplified)) {
                $result .= $regular;
                unset($regular);
                unset($simplified);
            }
            $simplified = '{ ';
            $regular    = '{ ';
            $values     = 0;
        } else if (compactify_slurp($blah, '/^\s*\[\s*/s', $matches)) {
            $levels[] = [ 'indent' => $indent ];
            $cursor += 2;
            $indent = $cursor;
            if (isset($simplified)) {
                $result .= $regular;
                unset($regular);
                unset($simplified);
            }
            $simplified = '[';
            $regular    = '[ ';
            $values     = 0;
        } else if (compactify_slurp($blah, '/^\s*(\"(?:[^"]+|\\\\")*\")\s*/s', $matches)) {
            if (isset($simplified)) {
                $simplified .= $matches[1][0];
                $regular .= $matches[1][0];
            } else {
                $result .= $matches[1][0];
            }
            $cursor += strlen($matches[1][0]);
        } else if (compactify_slurp($blah, '/^\s*(-?[0-9]+(?:\.[0-9]+)?(?:e[-+]?[0-9]+)?)\s*/s', $matches)) {
            if (isset($simplified)) {
                $simplified .= $matches[1][0];
                $regular .= $matches[1][0];
            } else {
                $result .= $matches[1][0];
            }
            $cursor += strlen($matches[1][0]);
        } else if (compactify_slurp($blah, '/^\s*(null)\b\s*/s', $matches)) {
            if (isset($simplified)) {
                $simplified .= $matches[1][0];
                $regular .= $matches[1][0];
            } else {
                $result .= $matches[1][0];
            }
            $cursor += strlen($matches[1][0]);
        } else if (compactify_slurp($blah, '/^\s*,\s*/s', $matches)) {
            if (isset($simplified)) {
                $simplified .= ", ";
                $regular .= ",\n" . str_repeat(' ', $indent);
                $values += 1;
            } else {
                $result .= ",\n" . str_repeat(' ', $indent);
            }
            $cursor = $indent;
        } else if (compactify_slurp($blah, '/^\s*:\s*/s', $matches)) {
            if (isset($simplified)) {
                $simplified .= ': ';
                $regular .= ': ';
            } else {
                $result .= ': ';
            }
            $cursor += 2;
        } else if (compactify_slurp($blah, '/^\s*\]\s*/s', $matches)) {
            if (isset($simplified)) {
                $simplified .= ']';
                $regular .= ' ]';
                if ($values) { $values += 1; }
                if ($values > 4) {
                    $result .= $regular;
                } else {
                    $result .= $simplified;
                }
                unset($simplified);
                unset($regular);
                unset($values);
            } else {
                $result .= ' ]';
            }
            $level = array_pop($levels);
            $indent = $level['indent'];
        } else if (compactify_slurp($blah, '/^\s*\}\s*/s', $matches)) {
            if (isset($simplified)) {
                $simplified .= ' }';
                $regular .= ' }';
                if ($values) { $values += 1; }
                if ($values > 4) {
                    $result .= $regular;
                } else {
                    $result .= $simplified;
                }
                unset($simplified);
                unset($regular);
                unset($values);
            } else {
                $result .= ' }';
            }
            $level = array_pop($levels);
            $indent = $level['indent'];
        } else {
            break;
        }
        error_log(strlen($json) . ' => ' . strlen($result));
    }
    if ($blah === '') {
        return $result;
    }
    return $json;
}
