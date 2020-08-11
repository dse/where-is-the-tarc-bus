<?php
class ASCIITable {
    public $headings = [];
    public $keys;
    public $rows = [];
    public $minColumnWidth = [];
    public $maxColumnWidth = [];
    public $wrapColumn = [];

    public function getMinColumnWidth($numericIndex) {
        $result = 0;
        $namedIndex = $this->getNamedIndex($numericIndex);
        if (array_key_exists($numericIndex, $this->minColumnWidth)) {
            $result = max($result, $this->minColumnWidth[$numericIndex]);
        }
        if (!empty($namedIndex) && array_key_exists($namedIndex, $this->minColumnWidth)) {
            $result = max($result, $this->minColumnWidth[$namedIndex]);
        }
        return $result;
    }
    public function getMaxColumnWidth($numericIndex) {
        $result = INF;
        $namedIndex = $this->getNamedIndex($numericIndex);
        if (array_key_exists($numericIndex, $this->maxColumnWidth)) {
            $result = min($result, $this->maxColumnWidth[$numericIndex]);
        }
        if (!empty($namedIndex) && array_key_exists($namedIndex, $this->maxColumnWidth)) {
            $result = min($result, $this->maxColumnWidth[$namedIndex]);
        }
        return $result;
    }
    public function getNamedIndex($numericIndex) {
        if (!$this->keys) {
            return null;
        }
        if (($index = @$this->keys[$numericIndex])) {
            return $index;
        }
        return null;
    }
    public function __toString() {
        $maxlen = [];
        $indices = [];
        for ($i = 0; $i < count($this->headings); $i += 1) {
            $maxlen[$i] = strlen($this->headings[$i]);
            if ($this->keys && count($this->keys) > $i) {
                $indices[] = $this->keys[$i];
            } else {
                $indices[] = $i;
            }
            $maxlen[$i] = max($maxlen[$i], $this->getMinColumnWidth($i));
        }
        for ($j = 0; $j < count($this->rows); $j += 1) {
            if ($this->rows[$j] === null) {
                continue;
            }
            for ($i = 0; $i < count($this->headings); $i += 1) {
                $key = $indices[$i];
                $data = array_key_exists($key, $this->rows[$j]) ? $this->rows[$j][$key] : null;
                if (isset($data)) {
                    $maxlen[$i] = max(strlen($data), $maxlen[$i]);
                }
            }
        }
        for ($i = 0; $i < count($this->headings); $i += 1) {
            $maxlen[$i] = min($maxlen[$i], $this->getMaxColumnWidth($i));
        }
        $format = array_map(function ($size) { return '%-' . $size . '.' . $size . 's'; }, $maxlen);
        $format = join('  ', $format);
        $rule = array_map(function ($size) { return str_repeat('-', $size); }, $maxlen);
        $rule = join('  ', $rule);
        $header = vsprintf($format, $this->headings);
        $result = $header . "\n";
        $result .= $rule . "\n";
        for ($j = 0; $j < count($this->rows); $j += 1) {
            if ($this->rows[$j] === null) {
                $result .= $rule . "\n";
            } else {
                $result .= vsprintf(
                    $format,
                    array_map(function ($i) use ($j) {
                        return array_key_exists($i, $this->rows[$j]) ? $this->rows[$j][$i] : '';
                    }, $indices)
                ) . "\n";
            }
        }
        return $result;
    }
}
