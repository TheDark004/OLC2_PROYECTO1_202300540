<?php

use Context\IntLitContext;
use Context\FloatLitContext;
use Context\StringLitContext;
use Context\BoolLitContext;
use Context\RuneLitContext;
use Context\NilLitContext;
use Context\AddExprContext;
use Context\MulExprContext;
use Context\NegExprContext;
use Context\GroupExprContext;
use Context\EqExprContext;
use Context\RelExprContext;
use Context\AndExprContext;
use Context\OrExprContext;
use Context\NotExprContext;
use Context\IdExprContext;

trait ExprHandler
{
    //  Literales 
    public function visitIntLit(IntLitContext $ctx): int
    {
        return intval($ctx->INT_LIT()->getText());
    }

    public function visitFloatLit(FloatLitContext $ctx): float
    {
        return floatval($ctx->FLOAT_LIT()->getText());
    }

    public function visitStringLit(StringLitContext $ctx): string
    {
        // Quitar las comillas dobles del string
        $raw = $ctx->STRING_LIT()->getText();
        return substr($raw, 1, strlen($raw) - 2);
    }

    public function visitBoolLit(BoolLitContext $ctx): bool
    {
        return $ctx->BOOL_LIT()->getText() === 'true';
    }

    public function visitRuneLit(RuneLitContext $ctx): string
    {
        // Quitar las comillas simples: 'a' -> a
        $raw = $ctx->RUNE_LIT()->getText();
        $char = substr($raw, 1, strlen($raw) - 2);
        return ord($char);
    }

    public function visitNilLit(NilLitContext $ctx): mixed
    {
        return null;
    }

    // Agrupación 
    public function visitGroupExpr(GroupExprContext $ctx): mixed
    {
        return $this->visit($ctx->e());
    }

    //  Referencia a variable 

    public function visitIdExpr($ctx): mixed
    {
        $name = $ctx->ID()->getText();
        try {
            $val = $this->env->get($name); 
            return $val;
        } catch (Exception $e) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => $e->getMessage(),
                'line' => $ctx->ID()->getSymbol()->getLine(),
                'col'  => $ctx->ID()->getSymbol()->getCharPositionInLine(),
            ];
            return null;
        }
    }

    //  Negación unaria 

    public function visitNegExpr(NegExprContext $ctx): mixed
    {
        $val = $this->visit($ctx->e());
        if ($val === null) return null;
        return -$val;
    }

    //  NOT lógico 
    public function visitNotExpr(NotExprContext $ctx): mixed
    {
        $val = $this->visit($ctx->e());
        if (!is_bool($val)) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => "Operador '!' solo aplica a bool",
                'line' => 0, 'col' => 0,
            ];
            return null;
        }
        return !$val;
    }

    // Suma y Resta 
    public function visitAddExpr(AddExprContext $ctx): mixed
    {
        $left  = $this->visit($ctx->e(0));
        $right = $this->visit($ctx->e(1));
        $op    = $ctx->op->getText();

        if ($left === null || $right === null) return null;

        // string + string = concatenación
        if ($op === '+' && is_string($left) && is_string($right)) {
            return $left . $right;
        }

        // Validar que sean numéricos
        if (!is_numeric($left) || !is_numeric($right)) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => "Operación '$op' inválida entre tipos incompatibles",
                'line' => 0, 'col' => 0,
            ];
            return null;
        }

        return $op === '+' ? $left + $right : $left - $right;
    }

    //  Multiplicación, División, Módulo 

    public function visitMulExpr(MulExprContext $ctx): mixed
    {
        $left  = $this->visit($ctx->e(0));
        $right = $this->visit($ctx->e(1));
        $op    = $ctx->op->getText();

        

        if ($left === null || $right === null) return null;

        // string * int = repetición
        if ($op === '*' && is_string($left) && is_int($right)) {
            return str_repeat($left, $right);
        }

        if (!is_numeric($left) || !is_numeric($right)) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => "Operación '$op' inválida entre tipos incompatibles",
                'line' => 0, 'col' => 0,
            ];
            return null;
        }

        return match($op) {
            '*' => $left * $right,
            '/' => $right == 0 ? $this->divisionByZero() : (is_int($left) && is_int($right) ? intdiv($left, $right) : $left / $right),
            '%' => $right == 0 ? $this->divisionByZero() : intval($left) % intval($right),
        };
    }

    private function divisionByZero(): null
    {
        $this->errors[] = [
            'type' => 'Semántico',
            'desc' => 'División por cero',
            'line' => 0, 'col' => 0,
        ];
        return null;
    }

    //  Relacionales 

    public function visitRelExpr(RelExprContext $ctx): mixed
    {
        $left  = $this->visit($ctx->e(0));
        $right = $this->visit($ctx->e(1));
        $op    = $ctx->op->getText();

        if ($left === null || $right === null) return null;

        return match($op) {
            '<'  => $left <  $right,
            '>'  => $left >  $right,
            '<=' => $left <= $right,
            '>=' => $left >= $right,
        };
    }

    // Igualdad 

    public function visitEqExpr(EqExprContext $ctx): mixed
    {
        $left  = $this->visit($ctx->e(0));
        $right = $this->visit($ctx->e(1));
        $op    = $ctx->op->getText();

        if ($left === null || $right === null) return null;

        return $op === '==' ? $left == $right : $left != $right;
    }

    //  AND con cortocircuito 

    public function visitAndExpr(AndExprContext $ctx): mixed
    {
        $left = $this->visit($ctx->e(0));
        if ($left === false) return false;   // cortocircuito
        return $this->visit($ctx->e(1));
    }

    // OR con cortocircuito 

    public function visitOrExpr(OrExprContext $ctx): mixed
    {
        $left = $this->visit($ctx->e(0));
        if ($left === true) return true;     // cortocircuito
        return $this->visit($ctx->e(1));
    }

    // len(s) 
    public function visitLenExpr($ctx): mixed
    {
        $val = $this->visit($ctx->e());

        if (is_string($val)) {
            return strlen($val);
        }
        if (is_array($val)) {
            return count($val);
        }

        $this->errors[] = [
            'type' => 'Semántico',
            'desc' => 'len() solo acepta string o arreglo',
            'line' => $ctx->getStart()->getLine(),
            'col'  => $ctx->getStart()->getCharPositionInLine(),
        ];
        return null;
    }

    // now()
    public function visitNowExpr($ctx): mixed
    {
        return date('Y-m-d H:i:s');
    }

    // substr(s, inicio, longitud)
    public function visitSubstrExpr($ctx): mixed
    {
        $s      = $this->visit($ctx->e(0));
        $inicio = $this->visit($ctx->e(1));
        $largo  = $this->visit($ctx->e(2));

        if (!is_string($s)) {
            $this->errors[] = ['type' => 'Semántico', 'desc' => 'substr() requiere un string como primer argumento', 'line' => 0, 'col' => 0];
            return null;
        }
        if ($inicio < 0 || $largo < 0 || $inicio + $largo > strlen($s)) {
            $this->errors[] = ['type' => 'Semántico', 'desc' => "substr(): índices inválidos ($inicio, $largo) para string de longitud " . strlen($s), 'line' => 0, 'col' => 0];
            return null;
        }

        return substr($s, $inicio, $largo);
    }

    // typeOf(x)
    public function visitTypeOfExpr($ctx): mixed
    {
        $val = $this->visit($ctx->e());

        if (is_bool($val))   return 'bool';
        if (is_int($val))    return 'int32';
        if (is_float($val))  return 'float32';
        if (is_string($val)) return 'string';
        if (is_array($val))  return 'arreglo';
        return 'nil';
    }

    // &x  retorna la ref
    public function visitRefExpr($ctx): mixed
    {
        $name = $ctx->ID()->getText();
        return ['__ref__' => true, 'name' => $name];
    }

    // *x  obtiene el valor de lo que se apunta
    public function visitDerefExpr($ctx): mixed
    {
        $name = $ctx->ID()->getText();
        try {
            $ref = $this->env->get($name);
            if (is_array($ref) && isset($ref['__ref__'])) {
                return $this->env->get($ref['name']);
            }
            // Si no es ref, trata el ID como puntero directamente
            return $this->env->get($ref);
        } catch (Exception $e) {
            $this->errors[] = ['type' => 'Semántico', 'desc' => $e->getMessage(), 'line' => 0, 'col' => 0];
            return null;
        }
    }
}