<?php
namespace Modules\PbsSummary\Includes;

/** Small, strict expression language. Never executes PHP or JavaScript. */
class TaskFilter {
    public const DEFAULT_EXPRESSION = "worker_type === 'reader' && status === 'connection error: not connected'";
    public const MAX_LENGTH = 255;
    private string $expression;
    private int $offset = 0;
    private ?array $tree = null;

    public function __construct(string $expression) {
        if (strlen($expression) > self::MAX_LENGTH) { throw new \InvalidArgumentException(I18n::t('Maximum 255 bytes allowed.')); }
        $this->expression = $expression;
        $this->space();
        if ($this->offset === strlen($expression)) { return; }
        $this->tree = $this->parseOr();
        $this->space();
        if ($this->offset !== strlen($expression)) { $this->invalid(I18n::t('Unexpected character')); }
    }
    private function invalid(string $message): void {
        throw new \InvalidArgumentException(I18n::tf('%s at position %d.', $message, $this->offset + 1));
    }
    private function space(): void {
        while ($this->offset < strlen($this->expression) && strpos(" \t\r\n", $this->expression[$this->offset]) !== false) { $this->offset++; }
    }
    private function take(string $token): bool {
        $this->space();
        if (substr($this->expression, $this->offset, strlen($token)) !== $token) { return false; }
        $this->offset += strlen($token);
        return true;
    }
    private function parseOr(): array {
        $node = $this->parseAnd();
        while ($this->take('||')) { $node = ['or', $node, $this->parseAnd()]; }
        return $node;
    }
    private function parseAnd(): array {
        $node = $this->parseAtom();
        while ($this->take('&&')) { $node = ['and', $node, $this->parseAtom()]; }
        return $node;
    }
    private function parseAtom(): array {
        if ($this->take('!')) { return ['not', $this->parseAtom()]; }
        if ($this->take('(')) {
            $node = $this->parseOr();
            if (!$this->take(')')) { $this->invalid(I18n::t('Closing parenthesis is missing')); }
            return $node;
        }
        $this->space();
        if (!preg_match('/\G[a-z_]+/', $this->expression, $match, 0, $this->offset)) { $this->invalid(I18n::t('Field name expected')); }
        $field = $match[0];
        if (!in_array($field, ['worker_type', 'status', 'worker_id', 'user', 'node'], true)) { $this->invalid(I18n::tf('Unknown field %s', $field)); }
        $this->offset += strlen($field);
        if ($this->take('===')) { $operator = 'eq'; }
        elseif ($this->take('!==')) { $operator = 'ne'; }
        else { $this->invalid(I18n::t('Comparison === or !== expected')); }
        return [$operator, $field, $this->literal()];
    }
    private function literal(): string {
        $this->space();
        $quote = $this->expression[$this->offset] ?? '';
        if ($quote !== "'" && $quote !== '"') { $this->invalid(I18n::t('Text in single or double quotes expected')); }
        $this->offset++;
        $value = '';
        while ($this->offset < strlen($this->expression)) {
            $char = $this->expression[$this->offset++];
            if ($char === $quote) { return $value; }
            if ($char === '\\') {
                $char = $this->expression[$this->offset] ?? '';
                if ($char !== $quote && $char !== '\\') { $this->invalid(I18n::t('Only quotes and backslashes may be escaped')); }
                $this->offset++;
            }
            $value .= $char;
        }
        $this->invalid(I18n::t('Closing quote is missing'));
    }
    public function matches(array $task): bool {
        return $this->tree !== null && $this->evaluate($this->tree, $task);
    }
    private function evaluate(array $node, array $task): bool {
        switch ($node[0]) {
            case 'or': return $this->evaluate($node[1], $task) || $this->evaluate($node[2], $task);
            case 'and': return $this->evaluate($node[1], $task) && $this->evaluate($node[2], $task);
            case 'not': return !$this->evaluate($node[1], $task);
            case 'eq': return ($task[$node[1]] ?? null) === $node[2];
            case 'ne': return ($task[$node[1]] ?? null) !== $node[2];
        }
        throw new \LogicException('Invalid filter node');
    }
}
