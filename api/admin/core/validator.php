<?php
/**
 * Input validation. Collects every field error, then check() throws one 422.
 *
 *   $v = new Validator($in);
 *   $name  = $v->str('name', 'Name', ['required' => true, 'max' => 80]);
 *   $price = $v->num('price', 'Price', ['required' => true, 'min' => 1]);
 *   $v->check();
 */
class Validator
{
    private array $in;
    private array $errors = [];

    public function __construct(array $in)
    {
        $this->in = $in;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->in) && $this->in[$key] !== '' && $this->in[$key] !== null;
    }

    public function error(string $key, string $message): void
    {
        $this->errors[$key] = $this->errors[$key] ?? $message;
    }

    public function check(): void
    {
        if ($this->errors) {
            throw new ApiException(reset($this->errors), 422, $this->errors);
        }
    }

    private function required(string $key, string $label, array $o): bool
    {
        if (!$this->has($key)) {
            if (!empty($o['required'])) {
                $this->error($key, "$label is required.");
            }
            return false;
        }
        return true;
    }

    /** Trimmed, tag-free string. */
    public function str(string $key, string $label, array $o = []): ?string
    {
        if (!$this->required($key, $label, $o)) {
            return $o['default'] ?? null;
        }
        $raw = $this->in[$key];
        if (!is_scalar($raw)) {
            $this->error($key, "$label is invalid.");
            return null;
        }
        $value = trim(empty($o['html']) ? strip_tags((string) $raw) : (string) $raw);
        if ($value === '' && !empty($o['required'])) {
            $this->error($key, "$label is required.");
            return null;
        }
        $len = mb_strlen($value);
        if (isset($o['min']) && $len < $o['min']) {
            $this->error($key, "$label must be at least {$o['min']} characters.");
        }
        if (isset($o['max']) && $len > $o['max']) {
            $this->error($key, "$label must be at most {$o['max']} characters.");
        }
        if (isset($o['pattern']) && !preg_match($o['pattern'], $value)) {
            $this->error($key, $o['pattern_message'] ?? "$label format is invalid.");
        }
        return $value;
    }

    public function int(string $key, string $label, array $o = []): ?int
    {
        if (!$this->required($key, $label, $o)) {
            return $o['default'] ?? null;
        }
        $raw = $this->in[$key];
        if (!is_scalar($raw) || filter_var($raw, FILTER_VALIDATE_INT) === false) {
            $this->error($key, "$label must be a whole number.");
            return null;
        }
        $value = (int) $raw;
        if (isset($o['min']) && $value < $o['min']) {
            $this->error($key, "$label must be at least {$o['min']}.");
        }
        if (isset($o['max']) && $value > $o['max']) {
            $this->error($key, "$label must be at most {$o['max']}.");
        }
        return $value;
    }

    public function num(string $key, string $label, array $o = []): ?float
    {
        if (!$this->required($key, $label, $o)) {
            return $o['default'] ?? null;
        }
        $raw = $this->in[$key];
        if (!is_scalar($raw) || !is_numeric($raw)) {
            $this->error($key, "$label must be a number.");
            return null;
        }
        $value = (float) $raw;
        if (isset($o['min']) && $value < $o['min']) {
            $this->error($key, "$label must be at least {$o['min']}.");
        }
        if (isset($o['max']) && $value > $o['max']) {
            $this->error($key, "$label must be at most {$o['max']}.");
        }
        return $value;
    }

    /** Checkbox / switch: on, 1, true, yes => true. Missing => $default. */
    public function bool(string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $this->in)) {
            return $default;
        }
        $raw = $this->in[$key];
        if (is_bool($raw)) {
            return $raw;
        }
        return in_array(strtolower(trim((string) $raw)), ['1', 'on', 'true', 'yes', 'active', 'enabled'], true);
    }

    public function email(string $key, string $label, array $o = []): ?string
    {
        $value = $this->str($key, $label, $o + ['max' => 100]);
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->error($key, "Enter a valid email address.");
        }
        return $value !== null ? strtolower($value) : null;
    }

    /** Indian mobile, returned as 10 digits. */
    public function mobile(string $key, string $label, array $o = []): ?string
    {
        $value = $this->str($key, $label, $o);
        if ($value === null || $value === '') {
            return $value;
        }
        $normalized = normalize_mobile($value);
        if ($normalized === null) {
            $this->error($key, "Enter a valid 10-digit mobile number.");
        }
        return $normalized;
    }

    public function date(string $key, string $label, array $o = []): ?string
    {
        $value = $this->str($key, $label, $o);
        if ($value === null || $value === '') {
            return $value === '' ? null : $value;
        }
        $d = DateTime::createFromFormat('Y-m-d', $value);
        if (!$d || $d->format('Y-m-d') !== $value) {
            $this->error($key, "$label must be a valid date (YYYY-MM-DD).");
            return null;
        }
        return $value;
    }

    /** HTML datetime-local ("2026-10-03T14:30") or "Y-m-d H:i[:s]". */
    public function datetime(string $key, string $label, array $o = []): ?string
    {
        $value = $this->str($key, $label, $o);
        if ($value === null || $value === '') {
            return null;
        }
        $ts = strtotime(str_replace('T', ' ', $value));
        if ($ts === false) {
            $this->error($key, "$label must be a valid date and time.");
            return null;
        }
        return date('Y-m-d H:i:s', $ts);
    }

    public function enum(string $key, string $label, array $allowed, array $o = []): ?string
    {
        $value = $this->str($key, $label, $o);
        if ($value === null || $value === '') {
            return $value === '' ? ($o['default'] ?? null) : $value;
        }
        foreach ($allowed as $option) {
            if (strcasecmp($option, $value) === 0) {
                return $option;
            }
        }
        $this->error($key, "$label is invalid.");
        return null;
    }

    /** Array of positive ints (e.g. services[]). */
    public function ids(string $key): array
    {
        $raw = $this->in[$key] ?? [];
        if (is_string($raw)) {
            $raw = $raw === '' ? [] : explode(',', $raw);
        }
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $raw), fn($i) => $i > 0)));
    }

    /** Array of short strings (e.g. areas[]). */
    public function strings(string $key, int $maxEach = 120): array
    {
        $raw = $this->in[$key] ?? [];
        if (is_string($raw)) {
            $raw = $raw === '' ? [] : explode(',', $raw);
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_scalar($item)) {
                $item = trim(strip_tags((string) $item));
                if ($item !== '') {
                    $out[] = mb_substr($item, 0, $maxEach);
                }
            }
        }
        return array_values(array_unique($out));
    }
}
