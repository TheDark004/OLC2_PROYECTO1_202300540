<?php

use Context\PrintlnStmtContext;
use Context\PrintlnExprContext;

trait PrintHandler
{
    public function visitPrintlnStmt(PrintlnStmtContext $ctx): mixed
    {
        $parts = [];
        foreach ($ctx->e() as $expr) {
            $erroresBefore = count($this->errors);
            $val    = $this->visit($expr);
            $erroresAfter = count($this->errors);

            if ($erroresAfter > $erroresBefore) {
                continue;
            }

            $parts[] = $this->valueToString($val);
        }
        if (!empty($parts)) {
            $this->console .= implode(' ', $parts) . "\n";
        }
        return null;
    }

    // fmt.Println usado como expresión 
    public function visitPrintlnExpr(PrintlnExprContext $ctx): mixed
    {
        return $this->visitPrintlnStmt($ctx);
    }

   private function valueToString(mixed $val): string
    {
        if ($val === null)  return '<nil>';
        if (is_bool($val)) return $val ? 'true' : 'false';
        if (is_string($val)) {
            
            return str_replace(
                ['\\n', '\\t', '\\r', '\\"', '\\\\'],
                ["\n",  "\t",  "\r",  '"',   "\\"],
                $val
            );
        }
        return (string) $val;
    }
}