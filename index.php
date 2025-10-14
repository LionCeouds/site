<?php

session_start();

$db_host = 'localhost';     
$db_usuario = 'root';      
$db_senha = '';             
$db_nome = 'sapore veto';    

$conn = new mysqli($db_host, $db_usuario, $db_senha, $db_nome);

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

function obterProdutos($conn) {
    $produtos = [];
    $sql = "SELECT * FROM produtos";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $produtos[$row['id']] = $row;
        }
    }
    return $produtos;
}


$produtos = obterProdutos($conn);

if (!isset($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

$admin_user = "admin";
$admin_pass = "sapore123";

if (isset($_POST['acao'])) {
    switch ($_POST['acao']) {
        case 'adicionar':
            $produto_id = (int)$_POST['produto_id'];
            if (isset($_SESSION['carrinho'][$produto_id])) {
                $_SESSION['carrinho'][$produto_id]['quantidade']++;
            } else {
                
                $sql = "SELECT * FROM produtos WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $produto_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $produto = $result->fetch_assoc();
                    $_SESSION['carrinho'][$produto_id] = [
                        'produto' => $produto,
                        'quantidade' => 1
                    ];
                }
            }
            break;
            
        case 'alterar_quantidade':
            $produto_id = (int)$_POST['produto_id'];
            $quantidade = (int)$_POST['quantidade'];
            if ($quantidade <= 0) {
                unset($_SESSION['carrinho'][$produto_id]);
            } else {
                $_SESSION['carrinho'][$produto_id]['quantidade'] = $quantidade;
            }
            break;
            
        case 'remover':
            $produto_id = (int)$_POST['produto_id'];
            unset($_SESSION['carrinho'][$produto_id]);
            break;
            
        case 'finalizar_pedido':
            
            $pedido_id = uniqid();
            $nome = $conn->real_escape_string($_POST['nome']);
            $telefone = $conn->real_escape_string($_POST['telefone']);
            $email = $conn->real_escape_string($_POST['email']);
            $cep = $conn->real_escape_string($_POST['cep']);
            $cidade = $conn->real_escape_string($_POST['cidade']);
            $endereco = $conn->real_escape_string($_POST['endereco']);
            $numero = $conn->real_escape_string($_POST['numero']);
            $complemento = $conn->real_escape_string($_POST['complemento']);
            $pagamento = $conn->real_escape_string($_POST['pagamento']);
            $frete = (float)$_POST['frete'];
            $total = calcularTotal() + $frete;
            
            $sql = "INSERT INTO pedidos (pedido_id, nome, telefone, email, cep, cidade, endereco, numero, complemento, pagamento, frete, total, data_pedido) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssssdd", $pedido_id, $nome, $telefone, $email, $cep, $cidade, $endereco, $numero, $complemento, $pagamento, $frete, $total);
            $stmt->execute();
            $pedido_db_id = $stmt->insert_id;
            
            foreach ($_SESSION['carrinho'] as $produto_id => $item) {
                $sql_item = "INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) 
                            VALUES (?, ?, ?, ?)";
                $stmt_item = $conn->prepare($sql_item);
                $stmt_item->bind_param("iiid", $pedido_db_id, $produto_id, $item['quantidade'], $item['produto']['preco']);
                $stmt_item->execute();
            }
            
            $_SESSION['pedido_finalizado'] = [
                'id' => $pedido_id,
                'dados' => $_POST,
                'itens' => $_SESSION['carrinho'],
                'total' => calcularTotal()
            ];
            $_SESSION['carrinho'] = [];
            header("Location: ?pagina=pedido_sucesso");
            exit;
    }
}

if (isset($_POST['login_admin'])) {
    if ($_POST['usuario'] === $admin_user && $_POST['senha'] === $admin_pass) {
        $_SESSION['admin_logado'] = true;
        header("Location: ?pagina=admin");
        exit;
    } else {
        $erro_login = "Usuário ou senha incorretos!";
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logado']);
    header("Location: index.php");
    exit;
}

if (isset($_SESSION['admin_logado']) && isset($_POST['acao_admin'])) {
    switch ($_POST['acao_admin']) {
        case 'adicionar_produto':
            $nome = $conn->real_escape_string($_POST['nome']);
            $preco = (float)$_POST['preco'];
            $categoria = $conn->real_escape_string($_POST['categoria']);
            $imagem = $conn->real_escape_string($_POST['imagem']);
            
            $sql = "INSERT INTO produtos (nome, preco, categoria, imagem) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sdss", $nome, $preco, $categoria, $imagem);
            $stmt->execute();
            $produtos = obterProdutos($conn);
            break;
            
        case 'editar_produto':
            $produto_id = (int)$_POST['produto_id'];
            $nome = $conn->real_escape_string($_POST['nome']);
            $preco = (float)$_POST['preco'];
            $categoria = $conn->real_escape_string($_POST['categoria']);
            $imagem = $conn->real_escape_string($_POST['imagem']);
            
            $sql = "UPDATE produtos SET nome = ?, preco = ?, categoria = ?, imagem = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sdssi", $nome, $preco, $categoria, $imagem, $produto_id);
            $stmt->execute();
            
            $produtos = obterProdutos($conn);
            break;
            
        case 'deletar_produto':
            $produto_id = (int)$_POST['produto_id'];
            
            $sql = "DELETE FROM produtos WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $produto_id);
            $stmt->execute();
            $produtos = obterProdutos($conn);
            break;
    }
}

function calcularTotal() {
    $total = 0;
    if (isset($_SESSION['carrinho'])) {
        foreach ($_SESSION['carrinho'] as $item) {
            $total += $item['produto']['preco'] * $item['quantidade'];
        }
    }
    return $total;
}

$pagina = isset($_GET['pagina']) ? $_GET['pagina'] : 'home';

if ($pagina === 'admin' && !isset($_SESSION['admin_logado'])) {
    $pagina = 'admin_login';
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sapore Vero - Os Melhores Pratos da Cozinha Italiana</title>
    <style>
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Georgia', serif;
        }
        
        body {
            background-color: #f8f5f0;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        header {
            background-color: #8B0000;
            color: white;
            padding: 20px 0;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo h1 {
            font-size: 2.5rem;
            margin-bottom: 5px;
        }
        
        .logo .subtitle {
            font-style: italic;
            font-size: 1rem;
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin-left: 20px;
        }
        
        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s;
        }
        
        nav ul li a:hover {
            color: #f0d9b5;
        }
        
        .carrinho-link {
            position: relative;
        }
        
        .carrinho-count {
            background: #f0d9b5;
            color: #8B0000;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.8rem;
            position: absolute;
            top: -8px;
            right: -8px;
        }
        
        .divider {
            height: 3px;
            background: linear-gradient(to right, transparent, #fff, transparent);
            margin: 10px auto;
            width: 80%;
        }
        
        .main-content {
            padding: 40px 0;
            min-height: 60vh;
        }
        
        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #8B0000;
            font-size: 2rem;
        }
        
        .produtos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .produto-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .produto-card:hover {
            transform: translateY(-5px);
        }
        
        .produto-imagem {
            height: 180px;
            background-color: #ddd;
            background-size: cover;
            background-position: center;
        }
        
        .produto-info {
            padding: 15px;
        }
        
        .produto-nome {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .produto-preco {
            color: #8B0000;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        
        .btn-adicionar {
            background: #8B0000;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
        }
        
        .btn-adicionar:hover {
            background: #a52a2a;
        }
        
        .carrinho-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #ddd;
            background: white;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        
        .carrinho-info {
            flex-grow: 1;
        }
        
        .carrinho-controles {
            display: flex;
            align-items: center;
        }
        
        .quantidade-controle {
            display: flex;
            align-items: center;
            margin-right: 15px;
        }
        
        .btn-quantidade {
            background: #8B0000;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .quantidade {
            margin: 0 10px;
            font-weight: bold;
        }
        
        .btn-remover {
            background: #ccc;
            color: #333;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .carrinho-total {
            text-align: right;
            margin-top: 20px;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .btn-finalizar {
            background: #8B0000;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.1rem;
            margin-top: 20px;
            display: block;
            width: 100%;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .admin-nav {
            background: #333;
            padding: 10px 0;
            margin-bottom: 20px;
        }
        
        .admin-nav ul {
            display: flex;
            list-style: none;
            justify-content: center;
        }
        
        .admin-nav ul li {
            margin: 0 15px;
        }
        
        .admin-nav ul li a {
            color: white;
            text-decoration: none;
        }
        
        .produtos-lista {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .produto-linha {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .acoes-produto button {
            margin-left: 5px;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-editar {
            background: #4CAF50;
            color: white;
        }
        
        .btn-excluir {
            background: #f44336;
            color: white;
        }
        
        footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 20px 0;
            margin-top: 40px;
        }
        
        .social-links {
            margin-top: 15px;
        }
        
        .social-links a {
            color: white;
            margin: 0 10px;
            text-decoration: none;
            font-size: 1.2rem;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            nav ul {
                margin-top: 15px;
                justify-content: center;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1>Sapore Vero</h1>
                    <p class="subtitle">OS MELHORES PRATOS DA COZINHA ITALIANA</p>
                </div>
                <nav>
                    <ul>
                        <li><a href="?pagina=home">Home</a></li>
                        <li><a href="?pagina=cardapio">Cardápio</a></li>
                        <li><a href="?pagina=localizacoes">Localizações</a></li>
                        <li class="carrinho-link">
                            <a href="?pagina=carrinho">
                                Carrinho 
                                <?php if (count($_SESSION['carrinho']) > 0): ?>
                                    <span class="carrinho-count"><?php echo count($_SESSION['carrinho']); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li><a href="?pagina=admin_login">Admin</a></li>
                    </ul>
                </nav>
            </div>
            <div class="divider"></div>
        </div>
    </header>
    
    <section class="main-content">
        <div class="container">
            <?php
            
            switch ($pagina) {
                case 'home':
                    includeHome();
                    break;
                case 'cardapio':
                    includeCardapio();
                    break;
                case 'carrinho':
                    includeCarrinho();
                    break;
                case 'finalizar_pedido':
                    includeFinalizarPedido();
                    break;
                case 'pedido_sucesso':
                    includePedidoSucesso();
                    break;
                case 'admin_login':
                    includeAdminLogin();
                    break;
                case 'admin':
                    includeAdmin();
                    break;
                case 'localizacoes':
                    includeLocalizacoes();
                    break;
                default:
                    includeHome();
            }
            ?>
        </div>
    </section>
    
    <footer>
        <div class="container">
            <h3>Siga-nos</h3>
            <div class="social-links">
                <a href="#">Instagram</a>
                <a href="#">Facebook</a>
                <a href="#">Twitter</a>
            </div>
            <p>&copy; <?php echo date("Y"); ?> Sapore Vero. Todos os direitos reservados.</p>
        </div>
    </footer>
</body>
</html>

<?php

function includeHome() {
    ?>
    <h2>Novas criações e edições limitadas</h2>
    <div style="display: flex; flex-wrap: wrap; justify-content: space-around; gap: 30px;">
        <div style="flex: 1; min-width: 300px; background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center;">
            <h3 style="color: #8B0000; margin-bottom: 15px;">Novo formulário de pedido</h3>
            <p>Experimente nossa nova forma de fazer pedidos, mais rápida e intuitiva.</p>
            <a href="?pagina=cardapio" style="display: inline-block; margin-top: 15px; background: #8B0000; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none;">Fazer Pedido</a>
        </div>
        <div style="flex: 1; min-width: 300px; background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center;">
            <h3 style="color: #8B0000; margin-bottom: 15px;">Nossas localizaçoes no RS</h3>
            <h2>Nossas Localizações</h2>
            <p style="text-align: center; margin-top: 20px;">
                📍 Unidade Centro - Rua Principal, 123<br>
                📍 Unidade Bairro - Avenida Itália, 456
            </p>
            <p>Estamos expandindo! Encontre-nos em novos pontos da cidade.</p>
            <a href="?pagina=localizacoes" style="display: inline-block; margin-top: 15px; background: #8B0000; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none;">Ver Localizações</a>
        </div>
        <div style="flex: 1; min-width: 300px; background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center;">
            <h3 style="color: #8B0000; margin-bottom: 15px;">Matérias e anúncios</h3>
            <p>Fique por dentro das novidades e promoções especiais.</p>
            <a href="#" style="display: inline-block; margin-top: 15px; background: #8B0000; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none;">Ver Notícias</a>
        </div>
    </div>
    <?php
}

function includeCardapio() {
    global $produtos;
    ?>
    <h2>Nosso Cardápio</h2>
    <div class="produtos-grid">
        <?php foreach ($produtos as $produto): ?>
            <div class="produto-card">
                <div class="produto-imagem" style="background-image: url('<?php echo $produto['imagem']; ?>');"></div>
                <div class="produto-info">
                    <div class="produto-nome"><?php echo htmlspecialchars($produto['nome']); ?></div>
                    <div class="produto-categoria"><?php echo htmlspecialchars($produto['categoria']); ?></div>
                    <div class="produto-preco">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></div>
                    <form method="post">
                        <input type="hidden" name="acao" value="adicionar">
                        <input type="hidden" name="produto_id" value="<?php echo $produto['id']; ?>">
                        <button type="submit" class="btn-adicionar">Adicionar ao Carrinho</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

function includeCarrinho() {
    ?>
    <h2>Seu Carrinho</h2>
    
    <?php if (empty($_SESSION['carrinho'])): ?>
        <p style="text-align: center; padding: 40px;">Seu carrinho está vazio.</p>
        <div style="text-align: center;">
            <a href="?pagina=cardapio" style="display: inline-block; background: #8B0000; color: white; padding: 12px 25px; border-radius: 4px; text-decoration: none;">Ver Cardápio</a>
        </div>
    <?php else: ?>
        <?php foreach ($_SESSION['carrinho'] as $produto_id => $item): ?>
            <div class="carrinho-item">
                <div class="carrinho-info">
                    <div class="produto-nome"><?php echo htmlspecialchars($item['produto']['nome']); ?></div>
                    <div class="produto-preco">R$ <?php echo number_format($item['produto']['preco'], 2, ',', '.'); ?> cada</div>
                </div>
                <div class="carrinho-controles">
                    <form method="post" class="quantidade-controle">
                        <input type="hidden" name="acao" value="alterar_quantidade">
                        <input type="hidden" name="produto_id" value="<?php echo $produto_id; ?>">
                        <button type="submit" name="quantidade" value="<?php echo $item['quantidade'] - 1; ?>" class="btn-quantidade">-</button>
                        <span class="quantidade"><?php echo $item['quantidade']; ?></span>
                        <button type="submit" name="quantidade" value="<?php echo $item['quantidade'] + 1; ?>" class="btn-quantidade">+</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="acao" value="remover">
                        <input type="hidden" name="produto_id" value="<?php echo $produto_id; ?>">
                        <button type="submit" class="btn-remover">Remover</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="carrinho-total">
            Total: R$ <?php echo number_format(calcularTotal(), 2, ',', '.'); ?>
        </div>
        
        <a href="?pagina=finalizar_pedido" class="btn-finalizar">Finalizar Pedido</a>
    <?php endif; ?>
    <?php
}

function includeFinalizarPedido() {
    ?>
    <h2>Finalizar Pedido</h2>
    
    <form method="post">
        <input type="hidden" name="acao" value="finalizar_pedido">
        
        <h3 style="margin-bottom: 20px; color: #8B0000;">Informações Pessoais</h3>
        <div class="form-row">
            <div class="form-group">
                <label for="nome">Nome Completo</label>
                <input type="text" id="nome" name="nome" required>
            </div>
            <div class="form-group">
                <label for="telefone">Telefone</label>
                <input type="tel" id="telefone" name="telefone" required>
            </div>
        </div>
        
        <div class="form-group">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <h3 style="margin: 30px 0 20px; color: #8B0000;">Endereço de Entrega</h3>
        <div class="form-row">
            <div class="form-group">
                <label for="cep">CEP</label>
                <input type="text" id="cep" name="cep" required onblur="calcularFrete(this.value)">
            </div>
            <div class="form-group">
                <label for="cidade">Cidade</label>
                <input type="text" id="cidade" name="cidade" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="endereco">Endereço</label>
                <input type="text" id="endereco" name="endereco" required>
            </div>
            <div class="form-group">
                <label for="numero">Número</label>
                <input type="text" id="numero" name="numero" required>
            </div>
        </div>
        
        <div class="form-group">
            <label for="complemento">Complemento</label>
            <input type="text" id="complemento" name="complemento">
        </div>
        
        <h3 style="margin: 30px 0 20px; color: #8B0000;">Forma de Pagamento</h3>
        <div class="form-group">
            <select id="pagamento" name="pagamento" required>
                <option value="">Selecione a forma de pagamento</option>
                <option value="cartao_credito">Cartão de Crédito</option>
                <option value="cartao_debito">Cartão de Débito</option>
                <option value="dinheiro">Dinheiro</option>
                <option value="pix">PIX</option>
            </select>
        </div>
        
        <div id="dados-cartao" style="display: none;">
            <div class="form-row">
                <div class="form-group">
                    <label for="numero_cartao">Número do Cartão</label>
                    <input type="text" id="numero_cartao" name="numero_cartao">
                </div>
                <div class="form-group">
                    <label for="validade">Validade</label>
                    <input type="text" id="validade" name="validade" placeholder="MM/AA">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="cvv">CVV</label>
                    <input type="text" id="cvv" name="cvv">
                </div>
                <div class="form-group">
                    <label for="nome_cartao">Nome no Cartão</label>
                    <input type="text" id="nome_cartao" name="nome_cartao">
                </div>
            </div>
        </div>
        
        <div class="carrinho-total">
            Subtotal: R$ <?php echo number_format(calcularTotal(), 2, ',', '.'); ?><br>
            <span id="frete-info">Frete: A calcular</span><br>
            <strong>Total: R$ <span id="total-final"><?php echo number_format(calcularTotal(), 2, ',', '.'); ?></span></strong>
        </div>
        
        <input type="hidden" id="frete" name="frete" value="0">
        
        <button type="submit" class="btn-finalizar">Confirmar Pedido</button>
    </form>
    
    <script>
        document.getElementById('pagamento').addEventListener('change', function() {
            var dadosCartao = document.getElementById('dados-cartao');
            if (this.value === 'cartao_credito' || this.value === 'cartao_debito') {
                dadosCartao.style.display = 'block';
            } else {
                dadosCartao.style.display = 'none';
            }
        });
        
        function calcularFrete(cep) {
            var frete = 15.00;
            document.getElementById('frete-info').textContent = 'Frete: R$ ' + frete.toFixed(2).replace('.', ',');
            
            var subtotal = <?php echo calcularTotal(); ?>;
            var total = subtotal + frete;
            document.getElementById('total-final').textContent = total.toFixed(2).replace('.', ',');
            
            document.getElementById('frete').value = frete;
        }
    </script>
    <?php
}

function includePedidoSucesso() {
    if (!isset($_SESSION['pedido_finalizado'])) {
        header("Location: ?pagina=home");
        exit;
    }
    
    $pedido = $_SESSION['pedido_finalizado'];
    ?>
    <div style="text-align: center; padding: 40px 0;">
        <h2 style="color: #4CAF50;">Pedido Realizado com Sucesso!</h2>
        <p style="font-size: 1.2rem; margin: 20px 0;">Obrigado por escolher o Sapore Vero!</p>
        <div style="background: white; padding: 20px; border-radius: 8px; max-width: 500px; margin: 0 auto;">
            <h3 style="color: #8B0000; margin-bottom: 15px;">Resumo do Pedido</h3>
            <p><strong>Número do Pedido:</strong> <?php echo $pedido['id']; ?></p>
            <p><strong>Nome:</strong> <?php echo htmlspecialchars($pedido['dados']['nome']); ?></p>
            <p><strong>Total:</strong> R$ <?php echo number_format($pedido['total'] + $pedido['dados']['frete'], 2, ',', '.'); ?></p>
        </div>
        <p style="margin-top: 20px;">Seu pedido está sendo preparado e chegará em aproximadamente 40 minutos.</p>
        <a href="?pagina=home" style="display: inline-block; margin-top: 20px; background: #8B0000; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none;">Voltar à Página Inicial</a>
    </div>
    <?php
    unset($_SESSION['pedido_finalizado']);
}

function includeAdminLogin() {
    global $erro_login;
    ?>
    <h2>Área do Administrador</h2>
    
    <div style="max-width: 400px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px;">
        <h3 style="text-align: center; margin-bottom: 20px; color: #8B0000;">Login</h3>
        
        <?php if (isset($erro_login)): ?>
            <p style="color: red; text-align: center; margin-bottom: 15px;"><?php echo $erro_login; ?></p>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label for="usuario">Usuário</label>
                <input type="text" id="usuario" name="usuario" required>
            </div>
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            <button type="submit" name="login_admin" class="btn-finalizar" style="width: 100%;">Entrar</button>
        </form>
    </div>
    <?php
}

function includeAdmin() {
    global $produtos, $conn;
    ?>
    <div class="admin-nav">
        <div class="container">
            <ul>
                <li><a href="?pagina=admin">Gerenciar Produtos</a></li>
                <li><a href="?pagina=admin&secao=pedidos">Pedidos</a></li>
                <li><a href="?pagina=admin&secao=relatorios">Relatórios</a></li>
                <li><a href="?logout=1">Sair</a></li>
            </ul>
        </div>
    </div>
    
    <h2>Área do Administrador</h2>
    
    <?php
    $secao = isset($_GET['secao']) ? $_GET['secao'] : 'produtos';
    
    switch ($secao) {
        case 'produtos':
            ?>
            <h3>Gerenciar Produtos</h3>
            
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                <h4><?php echo isset($_GET['editar']) ? 'Editar Produto' : 'Adicionar Novo Produto'; ?></h4>
                <form method="post">
                    <input type="hidden" name="acao_admin" value="<?php echo isset($_GET['editar']) ? 'editar_produto' : 'adicionar_produto'; ?>">
                    <?php if (isset($_GET['editar'])): ?>
                        <input type="hidden" name="produto_id" value="<?php echo $_GET['editar']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nome">Nome do Produto</label>
                            <input type="text" id="nome" name="nome" value="<?php if (isset($_GET['editar'])) echo $produtos[$_GET['editar']]['nome']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="preco">Preço (R$)</label>
                            <input type="number" id="preco" name="preco" step="0.01" value="<?php if (isset($_GET['editar'])) echo $produtos[$_GET['editar']]['preco']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="categoria">Categoria</label>
                            <select id="categoria" name="categoria" required>
                                <option value="">Selecione uma categoria</option>
                                <option value="Pizzas" <?php if (isset($_GET['editar']) && $produtos[$_GET['editar']]['categoria'] == 'Pizzas') echo 'selected'; ?>>Pizzas</option>
                                <option value="Massas" <?php if (isset($_GET['editar']) && $produtos[$_GET['editar']]['categoria'] == 'Massas') echo 'selected'; ?>>Massas</option>
                                <option value="Risotos" <?php if (isset($_GET['editar']) && $produtos[$_GET['editar']]['categoria'] == 'Risotos') echo 'selected'; ?>>Risotos</option>
                                <option value="Sobremesas" <?php if (isset($_GET['editar']) && $produtos[$_GET['editar']]['categoria'] == 'Sobremesas') echo 'selected'; ?>>Sobremesas</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="imagem">Imagem (URL)</label>
                            <input type="text" id="imagem" name="imagem" value="<?php if (isset($_GET['editar'])) echo $produtos[$_GET['editar']]['imagem']; ?>" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-finalizar" style="width: 100%;">
                        <?php echo isset($_GET['editar']) ? 'Salvar Alterações' : 'Adicionar Produto'; ?>
                    </button>
                </form>
            </div>

            <div class="produtos-lista">
                <h4>Lista de Produtos</h4>
                <?php foreach ($produtos as $produto): ?>
                    <div class="produto-linha">
                        <div>
                            <strong><?php echo htmlspecialchars($produto['nome']); ?></strong> - 
                            R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?> 
                            (<?php echo htmlspecialchars($produto['categoria']); ?>)
                        </div>
                        <div class="acoes-produto">
                            <a href="?pagina=admin&editar=<?php echo $produto['id']; ?>">
                                <button type="button" class="btn-editar">Editar</button>
                            </a>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="acao_admin" value="deletar_produto">
                                <input type="hidden" name="produto_id" value="<?php echo $produto['id']; ?>">
                                <button type="submit" class="btn-excluir">Excluir</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
            break;

        case 'pedidos':
            // Buscar pedidos do banco
            $sql = "SELECT * FROM pedidos ORDER BY data_pedido DESC";
            $result = $conn->query($sql);
            ?>
            <h3>Pedidos Realizados</h3>
            
            <?php if ($result->num_rows > 0): ?>
                <div class="produtos-lista">
                    <?php while($pedido = $result->fetch_assoc()): ?>
                        <div class="produto-linha">
                            <div>
                                <strong>Pedido #<?php echo $pedido['pedido_id']; ?></strong><br>
                                Cliente: <?php echo htmlspecialchars($pedido['nome']); ?><br>
                                Total: R$ <?php echo number_format($pedido['total'], 2, ',', '.'); ?><br>
                                Data: <?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?>
                            </div>
                            <div>
                                <a href="?pagina=admin&secao=detalhes_pedido&id=<?php echo $pedido['id']; ?>">
                                    <button type="button" class="btn-editar">Ver Detalhes</button>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>Nenhum pedido encontrado.</p>
            <?php endif; ?>
            <?php
            break;

        case 'detalhes_pedido':
            $pedido_id = (int)$_GET['id'];
            $sql = "SELECT * FROM pedidos WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $pedido_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $pedido = $result->fetch_assoc();
            $sql_itens = "SELECT pi.*, p.nome FROM pedido_itens pi 
                         JOIN produtos p ON pi.produto_id = p.id 
                         WHERE pi.pedido_id = ?";
            $stmt_itens = $conn->prepare($sql_itens);
            $stmt_itens->bind_param("i", $pedido_id);
            $stmt_itens->execute();
            $itens_result = $stmt_itens->get_result();
            ?>
            <h3>Detalhes do Pedido #<?php echo $pedido['pedido_id']; ?></h3>
            
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h4>Informações do Cliente</h4>
                <p><strong>Nome:</strong> <?php echo htmlspecialchars($pedido['nome']); ?></p>
                <p><strong>Telefone:</strong> <?php echo htmlspecialchars($pedido['telefone']); ?></p>
                <p><strong>E-mail:</strong> <?php echo htmlspecialchars($pedido['email']); ?></p>
                <p><strong>Endereço:</strong> <?php echo htmlspecialchars($pedido['endereco']); ?>, <?php echo htmlspecialchars($pedido['numero']); ?> - <?php echo htmlspecialchars($pedido['cidade']); ?> - CEP: <?php echo htmlspecialchars($pedido['cep']); ?></p>
                <p><strong>Forma de Pagamento:</strong> <?php echo htmlspecialchars($pedido['pagamento']); ?></p>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px;">
                <h4>Itens do Pedido</h4>
                <?php while($item = $itens_result->fetch_assoc()): ?>
                    <div style="display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #eee;">
                        <div><?php echo htmlspecialchars($item['nome']); ?></div>
                        <div>Quantidade: <?php echo $item['quantidade']; ?></div>
                        <div>R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></div>
                        <div>Subtotal: R$ <?php echo number_format($item['quantidade'] * $item['preco_unitario'], 2, ',', '.'); ?></div>
                    </div>
                <?php endwhile; ?>
                
                <div style="text-align: right; margin-top: 20px; font-size: 1.2rem;">
                    <strong>Total: R$ <?php echo number_format($pedido['total'], 2, ',', '.'); ?></strong>
                </div>
            </div>
            
            <a href="?pagina=admin&secao=pedidos" style="display: inline-block; margin-top: 20px; background: #8B0000; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none;">Voltar aos Pedidos</a>
            <?php
            break;

        case 'relatorios':
            ?>
            <h3>Relatórios</h3>
            <p>Aqui entrariam relatórios de vendas, clientes e faturamento.</p>
            <?php
            break;
    }
}

function includeLocalizacoes() {
    ?>
    <h2>Nossas Localizações</h2>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-top: 30px;">
        <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
            <h3 style="color: #8B0000; margin-bottom: 15px;">Unidade Centro</h3>
            <p><strong>Endereço:</strong> Rua Principal, 123 - Centro</p>
            <p><strong>Telefone:</strong> (51) 1234-5678</p>
            <p><strong>Horário de Funcionamento:</strong></p>
            <p>Segunda a Sexta: 11h às 23h</p>
            <p>Sábado e Domingo: 11h às 00h</p>
        </div>
        
        <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
            <h3 style="color: #8B0000; margin-bottom: 15px;">Unidade Bairro</h3>
            <p><strong>Endereço:</strong> Avenida Itália, 456 - Bairro</p>
            <p><strong>Telefone:</strong> (51) 9876-5432</p>
            <p><strong>Horário de Funcionamento:</strong></p>
            <p>Terça a Domingo: 18h às 23h</p>
            <p>Segunda: Fechado</p>
        </div>
    </div>
    <?php
}

$conn->close();

?>
