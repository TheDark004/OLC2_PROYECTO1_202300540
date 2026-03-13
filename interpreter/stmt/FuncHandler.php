<?php

trait FuncHandler
{
    public array $functions   = [];
    public bool  $returning   = false;
    public mixed $returnValue = null;

    public function hoistFunctions($ctx): void
    {
        foreach ($ctx->decl() as $decl) {

            if ($decl instanceof Context\ConstDeclGlobalContext) {
                $name  = $decl->ID()->getText();
                $value = $this->visit($decl->e());
                $type  = $decl->type_()->getText();
                $line  = $decl->ID()->getSymbol()->getLine();
                $col   = $decl->ID()->getSymbol()->getCharPositionInLine();
                $this->env->declare($name, $value);
                $this->env->declareConst($name);
                $this->addSymbol($name, "const $type", $value, $line, $col);
                continue;
            }

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

    public function visitFuncDeclMultiReturn($ctx): mixed
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
        $exprs = $ctx->e();

        if (count($exprs) === 0) {
            $this->returnValue = null;
        } elseif (count($exprs) === 1) {
            $this->returnValue = $this->visit($exprs[0]);
        } else {
            // Retorno múltiple se guarda como array con clave especial
            $valores = [];
            foreach ($exprs as $expr) {
                $valores[] = $this->visit($expr);
            }
            $this->returnValue = ['__multi__' => true, 'values' => $valores];
        }

        $this->returning = true;
        return null;
    }

    public function callFunction(string $name, array $args): mixed
    {
        // validacion para que main no pueda ser llamada asi misma 
        if ($name === 'main' && $this->env->scopeName !== 'global') {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => "La función 'main' no puede ser invocada explícitamente",
                'line' => 0, 'col' => 0,
            ];
            return null;
        }

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
            if ($child instanceof Context\ParametroContext ||  $child instanceof Context\ParametroArray1DContext ||
                $child instanceof Context\ParametroArray2DContext || $child instanceof Context\ParametroPointerArray1DContext ||
                $child instanceof Context\ParametroPointerArray2DContext) {

                $paramName  = $child->ID()->getText();
                $paramValue = $args[$argIndex] ?? null;
                $paramType  = $child->type_()->getText();
                $paramLine  = $child->ID()->getSymbol()->getLine();
                $paramCol   = $child->ID()->getSymbol()->getCharPositionInLine();


                $this->env->declare($paramName, $paramValue);
                $this->addSymbol($paramName, 'arreglo', $paramValue, $paramLine, $paramCol);
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
