<?php

use Context\VarDeclInitContext;
use Context\VarDeclEmptyContext;
use Context\ShortVarDeclContext;
use Context\AssignStmtContext;
use Context\PlusAssignStmtContext;
use Context\MinusAssignStmtContext;
use Context\StarAssignStmtContext;
use Context\SlashAssignStmtContext;
use Context\IncStmtContext;
use Context\DecStmtContext;

trait VarHandler
{
    //  var x int32 = 10 
    public function visitVarDeclInit(VarDeclInitContext $ctx): mixed
    {
        $name  = $ctx->ID()->getText();
        $value = $this->visit($ctx->e());

        // Verificar que no esté ya declarada en este scope
        if ($this->env->existsLocally($name)) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => "Variable '$name' ya declarada en este ámbito",
                'line' => $ctx->ID()->getSymbol()->getLine(),
                'col'  => $ctx->ID()->getSymbol()->getCharPositionInLine(),
            ];
            return null;
        }

        $this->env->declare($name, $value);
        return null;
    }

    //  var x int32  (sin valor, usa default del tipo) 
    public function visitVarDeclEmpty(VarDeclEmptyContext $ctx): mixed
    {
        $name  = $ctx->ID()->getText();
        $type  = $ctx->type_()->getText();

        if ($this->env->existsLocally($name)) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => "Variable '$name' ya declarada en este ámbito",
                'line' => $ctx->ID()->getSymbol()->getLine(),
                'col'  => $ctx->ID()->getSymbol()->getCharPositionInLine(),
            ];
            return null;
        }

        // Valor por defecto según el tipo
        $default = match($type) {
            'int32', 'int' => 0,
            'float32'      => 0.0,
            'bool'         => false,
            'rune'         => "\u{0000}",
            'string'       => '',
            default        => null,
        };

        $this->env->declare($name, $default);
        return null;
    }

    //  x := 10 
    public function visitShortVarDecl(ShortVarDeclContext $ctx): mixed
    {
        $name  = $ctx->ID()->getText();
        $value = $this->visit($ctx->e());

        if ($this->env->existsLocally($name)) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => "Variable '$name' ya declarada en este ámbito",
                'line' => $ctx->ID()->getSymbol()->getLine(),
                'col'  => $ctx->ID()->getSymbol()->getCharPositionInLine(),
            ];
            return null;
        }

        $this->env->declare($name, $value);
        return null;
    }

    //  x = 20 
    public function visitAssignStmt(AssignStmtContext $ctx): mixed
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

    // x += 5 
    public function visitPlusAssignStmt(PlusAssignStmtContext $ctx): mixed
    {
        return $this->compoundAssign($ctx, '+');
    }

    //  x -= 5 
    public function visitMinusAssignStmt(MinusAssignStmtContext $ctx): mixed
    {
        return $this->compoundAssign($ctx, '-');
    }

    // x *= 5 
    public function visitStarAssignStmt(StarAssignStmtContext $ctx): mixed
    {
        return $this->compoundAssign($ctx, '*');
    }

    // x /= 5 
    public function visitSlashAssignStmt(SlashAssignStmtContext $ctx): mixed
    {
        return $this->compoundAssign($ctx, '/');
    }

    // Helper compartido para +=, -=, *=, /=
    private function compoundAssign(mixed $ctx, string $op): null
    {
        $name  = $ctx->ID()->getText();
        $right = $this->visit($ctx->e());

        try {
            $left  = $this->env->get($name);
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
    public function visitIncStmt(IncStmtContext $ctx): mixed
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

    // x-- 
    public function visitDecStmt(DecStmtContext $ctx): mixed
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
}