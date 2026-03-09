<?php

use Context\PrintlnStmtContext;
use Context\PrintlnExprContext;

trait PrintHandler
{
    public function visitPrintlnStmt(PrintlnStmtContext $ctx): mixed
    {
        $parts = [];
        foreach ($ctx->e() as $expr) {
            $val    = $this->visit($expr);
            $parts[] = $this->valueToString($val);
        }
        $this->console .= implode(' ', $parts) . "\n";
        return null;
    }

    // fmt.Println usado como expresión (dentro de otra expr)
    public function visitPrintlnExpr(PrintlnExprContext $ctx): mixed
    {
        return $this->visitPrintlnStmt($ctx);
    }

    private function valueToString(mixed $val): string
    {
        if ($val === null)  return 'nil';
        if (is_bool($val)) return $val ? 'true' : 'false';
        return (string) $val;
    }
}