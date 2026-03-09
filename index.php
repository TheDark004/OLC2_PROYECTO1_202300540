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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input = $_POST["code"] ?? "";

    if (!empty(trim($input))) {
        try {
            $inputStream = InputStream::fromString($input);
            $lexer       = new GolampiLexer($inputStream);
            $tokens      = new CommonTokenStream($lexer);
            $parser      = new GolampiParser($tokens);
            $parser->setErrorHandler(new BailErrorStrategy());
            $tree        = $parser->program();
            $interpreter = new Interpreter();
            $output      = $interpreter->visit($tree);
            $errores     = $interpreter->errors;

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
    <title>Golampi Interpreter</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #1e1e2f;
            color: #f0f0f0;
            font-family: 'Segoe UI', Arial, sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ── Navbar ── */
        .navbar {
            background: #16162a;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid #3a3a5c;
            flex-shrink: 0;
        }
        .navbar h1 { font-size: 15px; color: #ffcc00; margin-right: auto; }
        .navbar button, .navbar input[type=submit] {
            background: #2e2e44;
            color: #f0f0f0;
            border: 1px solid #444;
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-family: inherit;
        }
        .navbar button:hover { background: #3a3a5c; }
        .btn-run {
            background: #ffcc00 !important;
            color: #1e1e2f !important;
            font-weight: bold !important;
            border-color: #ffcc00 !important;
        }
        .btn-run:hover { background: #e6b800 !important; }
        #fileInput { display: none; }

        /* ── Layout: editor arriba, consola abajo ── */
        .workspace {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ── Editor (parte de arriba) ── */
        .editor-section {
            height: calc(100vh - 52px - 280px); /* total - navbar - bottom */
            display: flex;
            flex-direction: column;
            border-bottom: 2px solid #3a3a5c;
            flex-shrink: 0;  /* no se encoge ni crece */
        }
        .section-title {
            background: #16162a;
            padding: 4px 12px;
            font-size: 11px;
            color: #888;
            border-bottom: 1px solid #3a3a5c;
            flex-shrink: 0;
        }
        .editor-wrap {
            flex: 1;
            display: flex;
            overflow: hidden;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5em;
        }
        .line-numbers {
            background: #2a2a3d;
            color: #555;
            padding: 10px 8px;
            text-align: right;
            white-space: pre;
            overflow: hidden;
            user-select: none;
            min-width: 40px;
            line-height: 1.5em;
            border-right: 1px solid #3a3a5c;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        textarea#code {
            flex: 1;
            background: #2e2e44;
            color: #f0f0f0;
            border: none;
            outline: none;
            resize: none;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5em;
            overflow: auto;
            white-space: pre;
            tab-size: 4;
        }

        /* ── Zona inferior: consola + pestañas ── */
        .bottom-section {
            height: 280px;
            display: flex;
            flex-shrink: 0;
        }

        /* ── Consola (izquierda del bottom) ── */
        .console-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #3a3a5c;
        }
        .console-out {
            flex: 1;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            white-space: pre-wrap;
            overflow: auto;
            background: #1a1a2e;
            color: #7ee787;
        }

        /* ── Reportes (derecha del bottom) ── */
        .reports-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .tabs {
            display: flex;
            background: #16162a;
            border-bottom: 1px solid #3a3a5c;
            flex-shrink: 0;
        }
        .tab {
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            color: #888;
            padding: 6px 14px;
            cursor: pointer;
            font-size: 12px;
            font-family: inherit;
        }
        .tab:hover { color: #f0f0f0; }
        .tab.active { color: #ffcc00; border-bottom-color: #ffcc00; }
        .badge {
            background: #c00;
            color: white;
            border-radius: 8px;
            padding: 0 5px;
            font-size: 10px;
            margin-left: 4px;
        }
        .tab-panel { display: none; flex: 1; overflow-y: auto; padding: 10px; }
        .tab-panel.active { display: block; }
        .placeholder { color: #666; font-size: 13px; }
        .sin-errores { color: #3fb950; font-size: 13px; }

        /* ── Tabla errores ── */
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { background: #16162a; color: #888; padding: 5px 10px; text-align: left; border-bottom: 1px solid #3a3a5c; position: sticky; top: 0; }
        td { padding: 4px 10px; border-bottom: 1px solid #2a2a3d; color: #ccc; }
        tr:hover td { background: #2a2a3d; }
        .tag { padding: 1px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; }
        .tag-sint { background: rgba(200,0,0,.2); color: #f85149; }
        .tag-sem  { background: rgba(188,140,255,.2); color: #bc8cff; }
        .tag-lex  { background: rgba(210,153,34,.2); color: #d29922; }
    </style>
</head>
<body>

<!-- Navbar -->
<div class="navbar">
    <h1>&#11042; Golampi Interpreter</h1>
    <button type="button" onclick="nuevoArchivo()">Nuevo</button>
    <button type="button" onclick="document.getElementById('fileInput').click()">Cargar</button>
    <input type="file" id="fileInput" accept=".go,.golampi,.txt" onchange="cargarArchivo(event)">
    <button type="button" onclick="guardarArchivo()">Guardar</button>
    <input type="submit" form="mainForm" class="btn-run" value="&#9654; Ejecutar">
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
            <div class="section-title">Consola de salida</div>
            <pre class="console-out"><?php echo htmlspecialchars($output); ?></pre>
        </div>

        <!-- Pestañas de reporte -->
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

            <div id="panelErrores" class="tab-panel active">
                <?php if (!isset($_POST['code'])): ?>
                    <p class="placeholder">Ejecuta el programa para ver los errores.</p>
                <?php elseif (empty($errores)): ?>
                    <p class="sin-errores"> </p>
                <?php else: ?>
                    <table>
                        <thead><tr><th>#</th><th>Tipo</th><th>Descripción</th><th>Línea</th><th>Columna</th></tr></thead>
                        <tbody>
                        <?php foreach ($errores as $i => $err): ?>
                            <?php $tag = match($err['type']) { 'Léxico' => 'tag-lex', 'Sintáctico' => 'tag-sint', default => 'tag-sem' }; ?>
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

            <div id="panelSimbolos" class="tab-panel">
                <p class="placeholder">La tabla de símbolos se mostrará aquí.</p>
            </div>
        </div>

    </div>
</div>
</form>

<script>
const codeEl   = document.getElementById("code")
const lineNums = document.getElementById("lineNumbers")

function actualizarLineas() {
    const n = codeEl.value.split("\n").length
    let nums = ""
    for (let i = 1; i <= n; i++) nums += i + "\n"
    lineNums.textContent = nums
}
function syncScroll() {
    lineNums.scrollTop = codeEl.scrollTop
}
function handleTab(e) {
    if (e.key !== "Tab") return
    e.preventDefault()
    const s = codeEl.selectionStart, en = codeEl.selectionEnd
    codeEl.value = codeEl.value.substring(0, s) + "    " + codeEl.value.substring(en)
    codeEl.selectionStart = codeEl.selectionEnd = s + 4
    actualizarLineas()
}
function nuevoArchivo() {
    if (codeEl.value.trim() !== "" && !confirm("¿Limpiar el editor?")) return
    codeEl.value = ""; actualizarLineas()
}
function cargarArchivo(e) {
    const f = e.target.files[0]; if (!f) return
    const r = new FileReader()
    r.onload = ev => { codeEl.value = ev.target.result; actualizarLineas() }
    r.readAsText(f); e.target.value = ""
}
function guardarArchivo() {
    const a = document.createElement("a")
    a.href = URL.createObjectURL(new Blob([codeEl.value], {type:"text/plain"}))
    a.download = "programa.golampi"; a.click()
}
function limpiarConsola() {
    document.querySelector(".console-out").textContent = ""
}
function switchTab(nombre) {
    document.querySelectorAll(".tab").forEach(t => t.classList.remove("active"))
    document.querySelectorAll(".tab-panel").forEach(p => p.classList.remove("active"))
    document.getElementById("tab" + nombre).classList.add("active")
    document.getElementById("panel" + nombre).classList.add("active")
}
actualizarLineas()
</script>

</body>
</html>