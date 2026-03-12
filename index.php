<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use Antlr\Antlr4\Runtime\InputStream;
use Antlr\Antlr4\Runtime\CommonTokenStream;
use Antlr\Antlr4\Runtime\Error\BailErrorStrategy;
use Antlr\Antlr4\Runtime\Error\Exceptions\ParseCancellationException;
use Antlr\Antlr4\Runtime\Error\Exceptions\InputMismatchException;

$input   = "";
$output  = "";
$errores = [];
$symbols = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input = $_POST["code"] ?? "";

    if (!empty(trim($input))) {
        try {
            $inputStream = InputStream::fromString($input);
            $lexer       = new GolampiLexer($inputStream);

            $lexerListener = new class implements \Antlr\Antlr4\Runtime\Error\Listeners\ANTLRErrorListener {
                public array $errors = [];
                public function syntaxError($recognizer, $offendingSymbol, int $line, int $charPositionInLine, string $msg, $e): void {
                    $this->errors[] = [
                        'type' => 'Léxico',
                        'desc' => "Token no reconocido: '" . trim(str_replace('token recognition error at:', '', $msg)) . "'",
                        'line' => $line,
                        'col'  => $charPositionInLine,
                    ];
                }
                public function reportAmbiguity(...$args): void {}
                public function reportAttemptingFullContext(...$args): void {}
                public function reportContextSensitivity(...$args): void {}
            };

            $lexer->removeErrorListeners();
            $lexer->addErrorListener($lexerListener);
            $tokens      = new CommonTokenStream($lexer);
            $parser      = new GolampiParser($tokens);
            $parser->setErrorHandler(new BailErrorStrategy());
            $tree        = $parser->program();
            $interpreter = new Interpreter();
            $output      = $interpreter->visit($tree);
            $errores     = array_merge($lexerListener->errors, $interpreter->errors);
            $symbols     = $interpreter->symbols;

        } catch (ParseCancellationException $e) {
            $cause = $e->getPrevious();
            if ($cause instanceof InputMismatchException) {
                $token     = $cause->getOffendingToken();
                $found     = $token ? $token->getText() : 'EOF';
                $errores[] = [
                    'type' => 'Sintáctico',
                    'desc' => "Token inesperado: '$found'",
                    'line' => $token?->getLine() ?? 0,
                    'col'  => $token?->getCharPositionInLine() ?? 0,
                ];
            } else {
                $errores[] = ['type' => 'Sintáctico', 'desc' => 'Error de sintaxis', 'line' => 0, 'col' => 0];
            }
        } catch (Exception $e) {
            $errores[] = ['type' => 'Error', 'desc' => $e->getMessage(), 'line' => 0, 'col' => 0];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Golampi-202300540</title>
    <link rel="stylesheet" href="/static/Style.css">
</head>
<body>

<!-- Navbar -->
<div class="navbar">
    <h1>René Gutiérrez &#11042; Golampi Interpreter &#11042; 202300540</h1>
    <button type="button" onclick="nuevoArchivo()">Nuevo</button>
    <button type="button" onclick="document.getElementById('fileInput').click()">Cargar</button>
    <input type="file" id="fileInput" accept=".go,.golampi,.txt" onchange="cargarArchivo(event)">
    <button type="button" onclick="guardarArchivo()">Guardar</button>
    <input type="submit" form="mainForm" class="btn-run" value="&#9654; Run">
    <button type="button" onclick="limpiarConsola()">Limpiar consola</button>
</div>

<form id="mainForm" method="POST">
<div class="workspace">

    <!-- Editor arriba -->
    <div class="editor-section">
        <div class="section-title">Código fuente</div>
        <div class="editor-wrap">
            <div class="line-numbers" id="lineNumbers">1</div>
            <textarea
                id="code" name="code"
                spellcheck="false"
                oninput="actualizarLineas()"
                onscroll="syncScroll()"
                onkeydown="handleTab(event)"
            ><?php echo htmlspecialchars($input); ?></textarea>
        </div>
    </div>

    <!-- Consola + Reportes abajo -->
    <div class="bottom-section">

        <!-- Consola -->
        <div class="console-wrap">
            <div class="section-title">Salida</div>
            <pre class="console-out"><?php echo htmlspecialchars($output); ?></pre>
        </div>

        <!-- Pestañas -->
        <div class="reports-wrap">
            <div class="tabs">
                <button type="button" class="tab active" id="tabErrores" onclick="switchTab('Errores')">
                    Errores
                    <?php if (!empty($errores)): ?>
                        <span class="badge"><?= count($errores) ?></span>
                    <?php endif; ?>
                </button>
                <button type="button" class="tab" id="tabSimbolos" onclick="switchTab('Simbolos')">
                    Tabla de Símbolos  
                </button>
            </div>

            <!-- Panel Errores -->
            <div id="panelErrores" class="tab-panel active">
                <?php if (!isset($_POST['code'])): ?>
                    <p class="placeholder"></p>
                <?php elseif (empty($errores)): ?>
                    <p class="sin-errores"></p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr><th>#</th><th>Tipo</th><th>Descripción</th><th>Línea</th><th>Columna</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($errores as $i => $err): ?>
                            <?php $tag = match($err['type']) {
                                'Léxico'     => 'tag-lex',
                                'Sintáctico' => 'tag-sint',
                                default      => 'tag-sem'
                            }; ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><span class="tag <?= $tag ?>"><?= htmlspecialchars($err['type']) ?></span></td>
                                <td><?= htmlspecialchars($err['desc']) ?></td>
                                <td><?= $err['line'] ?></td>
                                <td><?= $err['col'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Panel Símbolos -->
            <div id="panelSimbolos" class="tab-panel">
                <?php if (!isset($_POST['code'])): ?>
                    <p class="placeholder"></p>
                <?php elseif (empty($symbols)): ?>
                    <p class="placeholder">No se encontraron símbolos.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr><th>#</th><th>Identificador</th><th>Tipo</th><th>Ámbito</th><th>Valor</th><th>Línea</th><th>Columna</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($symbols as $i => $sym): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= htmlspecialchars($sym['name']) ?></strong></td>
                                <td><?= htmlspecialchars($sym['type']) ?></td>
                                <td><span class="tag tag-scope"><?= htmlspecialchars($sym['scope']) ?></span></td>
                                <td><?= htmlspecialchars($sym['value']) ?></td>
                                <td><?= $sym['line'] ?></td>
                                <td><?= $sym['col'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
</form>

<script src="/static/Script.js"></script>
</body>
</html>