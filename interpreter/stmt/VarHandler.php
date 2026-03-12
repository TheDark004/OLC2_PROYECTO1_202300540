<?php

trait VarHandler
{
    //  var x int32 = 10
    public function visitVarDeclInit($ctx): mixed
    {
        $name  = $ctx->ID()->getText();
        $value = $this->visit($ctx->e());
        $type  = $ctx->type_() !== null ? $ctx->type_()->getText() : $this->inferType($value);
        $line  = $ctx->ID()->getSymbol()->getLine();
        $col   = $ctx->ID()->getSymbol()->getCharPositionInLine();

        $this->env->declare($name, $value);
        $this->addSymbol($name, $type, $value, $line, $col);
        return null;
    }

    //  var x int32  (sin valor)
    public function visitVarDeclEmpty($ctx): mixed
    {
        $name = $ctx->ID()->getText();
        $type = $ctx->type_() !== null ? $ctx->type_()->getText() : 'unknown';
        $line = $ctx->ID()->getSymbol()->getLine();
        $col  = $ctx->ID()->getSymbol()->getCharPositionInLine();

        if ($this->env->existsLocally($name)) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => "Variable '$name' ya declarada en este ámbito",
                'line' => $line,
                'col'  => $col,
            ];
            return null;
        }

        $default = match($type) {
            'int32', 'int' => 0,
            'float32'      => 0.0,
            'bool'         => false,
            'rune'         => "\u{0000}",
            'string'       => '',
            default        => null,
        };

        $this->env->declare($name, $default);
        $this->addSymbol($name, $type, $default, $line, $col);
        return null;
    }

    //  x := 10
    public function visitShortVarDecl($ctx): mixed
    {
        $name  = $ctx->ID()->getText();
        $value = $this->visit($ctx->e());
        $type  = $this->inferType($value);
        $line  = $ctx->ID()->getSymbol()->getLine();
        $col   = $ctx->ID()->getSymbol()->getCharPositionInLine();

        if ($this->env->existsLocally($name)) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => "Variable '$name' ya declarada en este ámbito",
                'line' => $line,
                'col'  => $col,
            ];
            return null;
        }

        $this->env->declare($name, $value);
        $this->addSymbol($name, $type, $value, $line, $col);
        return null;
    }

    //  x = 20
    public function visitAssignStmt($ctx): mixed
    {
        $name  = $ctx->ID()->getText();
        $value = $this->visit($ctx->e());

        try {
            $this->env->set($name, $value);
        } catch (Exception $e) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => $e->getMessage(),
                'line' => $ctx->ID()->getSymbol()->getLine(),
                'col'  => $ctx->ID()->getSymbol()->getCharPositionInLine(),
            ];
        }
        return null;
    }

    //  x += 5
    public function visitPlusAssignStmt($ctx): mixed
    {
        return $this->compoundAssign($ctx, '+');
    }

    //  x -= 5
    public function visitMinusAssignStmt($ctx): mixed
    {
        return $this->compoundAssign($ctx, '-');
    }

    //  x *= 5
    public function visitStarAssignStmt($ctx): mixed
    {
        return $this->compoundAssign($ctx, '*');
    }

    //  x /= 5
    public function visitSlashAssignStmt($ctx): mixed
    {
        return $this->compoundAssign($ctx, '/');
    }

    // Helper compartido para +=, -=, *=, /=
    private function compoundAssign(mixed $ctx, string $op): null
    {
        $name  = $ctx->ID()->getText();
        $right = $this->visit($ctx->e());

        try {
            $left   = $this->env->get($name);
            $result = match($op) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                '/' => $right == 0 ? null : $left / $right,
            };
            $this->env->set($name, $result);
        } catch (Exception $e) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => $e->getMessage(),
                'line' => $ctx->ID()->getSymbol()->getLine(),
                'col'  => $ctx->ID()->getSymbol()->getCharPositionInLine(),
            ];
        }
        return null;
    }

    //  x++
    public function visitIncStmt($ctx): mixed
    {
        $name = $ctx->ID()->getText();
        try {
            $this->env->set($name, $this->env->get($name) + 1);
        } catch (Exception $e) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => $e->getMessage(),
                'line' => $ctx->ID()->getSymbol()->getLine(),
                'col'  => $ctx->ID()->getSymbol()->getCharPositionInLine(),
            ];
        }
        return null;
    }

    //  x--
    public function visitDecStmt($ctx): mixed
    {
        $name = $ctx->ID()->getText();
        try {
            $this->env->set($name, $this->env->get($name) - 1);
        } catch (Exception $e) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => $e->getMessage(),
                'line' => $ctx->ID()->getSymbol()->getLine(),
                'col'  => $ctx->ID()->getSymbol()->getCharPositionInLine(),
            ];
        }
        return null;
    }

    public function addSymbol(string $name, ?string $type, mixed $value, int $line, int $col): void
    {
        $this->symbols[] = [
            'name'  => $name,
            'type'  => $type ?? 'unknown',
            'scope' => $this->env->scopeName,
            'value' => $this->formatValue($value),
            'line'  => $line,
            'col'   => $col,
        ];
    }

    public function formatValue(mixed $value): string
    {
        if ($value === null)  return '—';
        if ($value === true)  return 'true';
        if ($value === false) return 'false';
        return strval($value);
    }

    public function inferType(mixed $value): string
    {
        if (is_bool($value))   return 'bool';
        if (is_int($value))    return 'int32';
        if (is_float($value))  return 'float32';
        if (is_string($value)) return 'string';
        return 'unknown';
    }

    public function visitMultiShortVarDecl($ctx): mixed
    {
        $ids   = $ctx->ID();
        $expr = $this->visit($ctx->e(0));

        // Expandir retorno múltiple
        $valores = [];
        if (is_array($expr) && isset($expr['__multi__'])) {
            $valores = $expr['values'];
        } else {
            $valores[] = $expr;
        }

        // Asignar a cada ID
        foreach ($ids as $i => $id) {
            $name  = $id->getText();
            $value = $valores[$i] ?? null;
            $type  = $this->inferType($value);
            $line  = $id->getSymbol()->getLine();
            $col   = $id->getSymbol()->getCharPositionInLine();

            $this->env->declare($name, $value);
            $this->addSymbol($name, $type, $value, $line, $col);
        }

        return null;
    }

    // *x = valor
    public function visitDerefAssignStmt($ctx): mixed
    {
        $name  = $ctx->ID()->getText();
        $value = $this->visit($ctx->e());

        try {
            $ref = $this->env->get($name);
            if (is_array($ref) && isset($ref['__ref__'])) {
                $this->env->set($ref['name'], $value);
            } else {
                $this->errors[] = ['type' => 'Semántico', 'desc' => "'$name' no es un puntero", 'line' => 0, 'col' => 0];
            }
        } catch (Exception $e) {
            $this->errors[] = ['type' => 'Semántico', 'desc' => $e->getMessage(), 'line' => 0, 'col' => 0];
        }
        return null;
    }

    // const max int32 = 100
    public function visitConstDeclStmt($ctx): mixed
    {
        $name  = $ctx->ID()->getText();
        $value = $this->visit($ctx->e());
        $type  = $ctx->type_()->getText();
        $line  = $ctx->ID()->getSymbol()->getLine();
        $col   = $ctx->ID()->getSymbol()->getCharPositionInLine();

        if ($this->env->existsLocally($name)) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => "Constante '$name' ya declarada en este ámbito",
                'line' => $line,
                'col'  => $col,
            ];
            return null;
        }

        $this->env->declare($name, $value);
        $this->env->declareConst($name); // marcar como constante
        $this->addSymbol($name, "const $type", $value, $line, $col);
        return null;
    }
}