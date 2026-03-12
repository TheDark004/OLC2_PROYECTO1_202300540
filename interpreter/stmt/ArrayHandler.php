<?php

trait ArrayHandler
{
    // var a [5]int
    public function visitVarArray1D($ctx): mixed
    {
        $name = $ctx->ID()->getText();
        $size = (int) $ctx->arrayType1D()->INT_LIT()->getText();
        $type = $ctx->type_()->getText();
        $line = $ctx->ID()->getSymbol()->getLine();
        $col  = $ctx->ID()->getSymbol()->getCharPositionInLine();

        $arr = array_fill(0, $size, $this->arrayDefault($type));

        $this->env->declare($name, $arr);
        $this->addSymbol($name, "[$size]$type", '—', $line, $col);
        return null;
    }

    // var b [3]int = [3]int{1, 2, 3}
    public function visitVarArray1DInit($ctx): mixed
    {
        $name = $ctx->ID()->getText();
        $size = (int) $ctx->arrayType1D()->INT_LIT()->getText();
        $type = $ctx->type_(0)->getText();
        $line = $ctx->ID()->getSymbol()->getLine();
        $col  = $ctx->ID()->getSymbol()->getCharPositionInLine();

        $arr = array_fill(0, $size, $this->arrayDefault($type));

        $i = 0;
        foreach ($ctx->arrayLit1D()->e() as $expr) {
            if ($i >= $size) break;
            $arr[$i] = $this->visit($expr);
            $i++;
        }

        $this->env->declare($name, $arr);
        $this->addSymbol($name, "[$size]$type", '—', $line, $col);
        return null;
    }

    // var m [2][3]int
    public function visitVarArray2D($ctx): mixed
    {
        $name = $ctx->ID()->getText();
        $rows = (int) $ctx->arrayType2D()->INT_LIT(0)->getText();
        $cols = (int) $ctx->arrayType2D()->INT_LIT(1)->getText();
        $type = $ctx->type_()->getText();
        $line = $ctx->ID()->getSymbol()->getLine();
        $col  = $ctx->ID()->getSymbol()->getCharPositionInLine();

        $arr = [];
        for ($i = 0; $i < $rows; $i++) {
            $arr[$i] = array_fill(0, $cols, $this->arrayDefault($type));
        }

        $this->env->declare($name, $arr);
        $this->addSymbol($name, "[$rows][$cols]$type", '—', $line, $col);
        return null;
    }

    // var mat [2][2]int = [2][2]int{{1,2},{3,4}}
    public function visitVarArray2DInit($ctx): mixed
    {
        $name = $ctx->ID()->getText();
        $rows = (int) $ctx->arrayType2D()->INT_LIT(0)->getText();
        $cols = (int) $ctx->arrayType2D()->INT_LIT(1)->getText();
        $type = $ctx->type_(0)->getText();
        $line = $ctx->ID()->getSymbol()->getLine();
        $col  = $ctx->ID()->getSymbol()->getCharPositionInLine();

        $arr = [];
        for ($i = 0; $i < $rows; $i++) {
            $arr[$i] = array_fill(0, $cols, $this->arrayDefault($type));
        }

        $i = 0;
        foreach ($ctx->arrayLit2D()->arrayRow() as $fila) {
            if ($i >= $rows) break;
            $j = 0;
            foreach ($fila->e() as $expr) {
                if ($j >= $cols) break;
                $arr[$i][$j] = $this->visit($expr);
                $j++;
            }
            $i++;
        }

        $this->env->declare($name, $arr);
        $this->addSymbol($name, "[$rows][$cols]$type", '—', $line, $col);
        return null;
    }

    // nums[0] = 10
    public function visitArrayAssign1D($ctx): mixed
    {
        $name  = $ctx->ID()->getText();
        $index = $this->visit($ctx->e(0));
        $value = $this->visit($ctx->e(1));

        try {
            $val = $this->env->get($name);
            
            // Si es puntero
            $realName = $name;
            if (is_array($val) && isset($val['__ref__'])) {
                $realName = $val['name'];
                $val = $this->env->get($realName);
            }

            if (!is_array($val)) {
                $this->errors[] = ['type' => 'Semántico', 'desc' => "'$name' no es un arreglo", 'line' => 0, 'col' => 0];
                return null;
            }
            if ($index < 0 || $index >= count($val)) {
                $this->errors[] = ['type' => 'Semántico', 'desc' => "Índice $index fuera de rango en '$name'", 'line' => 0, 'col' => 0];
                return null;
            }
            $val[$index] = $value;
            $this->env->set($realName, $val);
        } catch (Exception $ex) {
            $this->errors[] = ['type' => 'Semántico', 'desc' => $ex->getMessage(), 'line' => 0, 'col' => 0];
        }
        return null;
    }

    // grid[0][1] = 5
    public function visitArrayAssign2D($ctx): mixed
    {
        $name  = $ctx->ID()->getText();
        $fila  = $this->visit($ctx->e(0));
        $col   = $this->visit($ctx->e(1));
        $value = $this->visit($ctx->e(2));

        try {
            $arr = $this->env->get($name);
            if (!is_array($arr)) {
                $this->errors[] = ['type' => 'Semántico', 'desc' => "'$name' no es un arreglo", 'line' => 0, 'col' => 0];
                return null;
            }
            $arr[$fila][$col] = $value;
            $this->env->set($name, $arr);
        } catch (Exception $ex) {
            $this->errors[] = ['type' => 'Semántico', 'desc' => $ex->getMessage(), 'line' => 0, 'col' => 0];
        }
        return null;
    }

    // nums[i]
    public function visitArrayAccess1D($ctx): mixed
    {
        $name  = $ctx->ID()->getText();
        $index = $this->visit($ctx->e());

        try {
            $val = $this->env->get($name);

            if (is_array($val) && isset($val['__ref__'])) {
                $val = $this->env->get($val['name']);
            }

            if (!is_array($val)) {
                $this->errors[] = ['type' => 'Semántico', 'desc' => "'$name' no es un arreglo", 'line' => 0, 'col' => 0];
                return null;
            }
            if ($index < 0 || $index >= count($val)) {
                $this->errors[] = ['type' => 'Semántico', 'desc' => "Índice $index fuera de rango en '$name'", 'line' => 0, 'col' => 0];
                return null;
            }
            return $val[$index];
        } catch (Exception $ex) {
            $this->errors[] = ['type' => 'Semántico', 'desc' => $ex->getMessage(), 'line' => 0, 'col' => 0];
            return null;
        }
    }

    // mat[i][j]
    public function visitArrayAccess2D($ctx): mixed
    {
        $name = $ctx->ID()->getText();
        $fila = $this->visit($ctx->e(0));
        $col  = $this->visit($ctx->e(1));

        try {
            $arr = $this->env->get($name);
            if (!is_array($arr)) {
                $this->errors[] = ['type' => 'Semántico', 'desc' => "'$name' no es un arreglo", 'line' => 0, 'col' => 0];
                return null;
            }
            return $arr[$fila][$col];
        } catch (Exception $ex) {
            $this->errors[] = ['type' => 'Semántico', 'desc' => $ex->getMessage(), 'line' => 0, 'col' => 0];
            return null;
        }
    }

    // Valor por defecto ssegun su tipo
    private function arrayDefault(string $type): mixed
    {
        return match($type) {
            'int32', 'int' => 0,
            'float32'      => 0.0,
            'bool'         => false,
            'string'       => '',
            'rune'         => 0,
            default        => null,
        };
    }
}