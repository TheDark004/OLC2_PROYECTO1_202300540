<?php

trait FuncHandler
{
    public array $functions   = [];
    public bool  $returning   = false;
    public mixed $returnValue = null;

    public function hoistFunctions($ctx): void
    {
        foreach ($ctx->decl() as $decl) {
            $funcCtx = $decl->funcDecl();
            if ($funcCtx === null) continue;

            $name = $funcCtx->ID()->getText();
            $line = $funcCtx->ID()->getSymbol()->getLine();
            $col  = $funcCtx->ID()->getSymbol()->getCharPositionInLine();

            $this->functions[$name] = $funcCtx;

            // Registra funcion en símbolos
            $this->symbols[] = [
                'name'  => $name,
                'type'  => 'función',
                'scope' => 'global',
                'value' => '—',
                'line'  => $line,
                'col'   => $col,
            ];
        }
    }

    public function visitFuncDeclVoid($ctx): mixed
    {
        return null;
    }

    public function visitFuncDeclReturn($ctx): mixed
    {
        return null;
    }

    public function visitFuncCallStmt($ctx): mixed
    {
        $name = $ctx->ID()->getText();
        $args = [];
        foreach ($ctx->e() as $expr) {
            $args[] = $this->visit($expr);
        }
        return $this->callFunction($name, $args);
    }

    public function visitFuncCallExpr($ctx): mixed
    {
        $name = $ctx->ID()->getText();
        $args = [];
        foreach ($ctx->e() as $expr) {
            $args[] = $this->visit($expr);
        }
        return $this->callFunction($name, $args);
    }

    public function visitReturnStmt($ctx): mixed
    {
        if ($ctx->e() !== null) {
            $this->returnValue = $this->visit($ctx->e());
        } else {
            $this->returnValue = null;
        }
        $this->returning = true;
        return null;
    }

    public function callFunction(string $name, array $args): mixed
    {
        if (!isset($this->functions[$name])) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => "Función '$name' no declarada",
                'line' => 0, 'col' => 0,
            ];
            return null;
        }

        $funcCtx   = $this->functions[$name];
        $previous  = $this->env;
        $this->env = new Environment($previous,$name);

    
        $argIndex = 0;
        foreach ($funcCtx->children as $child) {
            if ($child instanceof Context\ParametroContext) {
                $paramName  = $child->ID()->getText();
                $paramValue = $args[$argIndex] ?? null;
                $this->env->declare($paramName, $paramValue);
                $argIndex++;
            }
        }

        $this->visit($funcCtx->block());

        $result           = $this->returnValue;
        $this->returning  = false;
        $this->returnValue = null;
        $this->env        = $previous;

        return $result;
    }
}
