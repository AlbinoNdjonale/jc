# JC

**autor**: Albino Ndjonale &lt;albinondjonale1@gmail.com&gt;
<br>
**versão**: 1.0.0

Jc é framework web feito em php, que visa ser facil e simples de compreender.

## Para iniciar um projecto

```bush
git clone https://github.com/AlbinoNdjonale/jc.git projecto
```

## Estrutura inicial de um projecto JC

```bush
.
│
├── jc
│   ├── index.php
│   ├── middleware
│   │   └── middleware.php
│   ├── model
│   │   └── model.php
│   ├── pages
│   │   └── pages.php
│   ├── qbuilder
│   │   └── qbuilder.php
│   ├── queue
│   │   ├── push.php
│   │   └── queue.php
│   ├── util
│   │   └── util.php
│   ├── storage
│   │   ├── cashe
│   │   ├── channel_stream
│   │   ├── log
│   │   └── queue
│   ├── http
│   │   ├── response.php
│   │   └── request.php
│   ├── backgroundplain.php
│   ├── test.php
│   └── helpers.php
├── index.php
├── README.md
├── .env
└── .htaccess
```

O arquivo ./.env é o arquivo usado para configurar o seu projecto.

Inicialmente ele contera o seguite conteudo

```properties
DOMAIN=localhost/jc

DEV=true
```

O atributo DEV diz se a applicação está no modo de desenvolvimento ao não.

O arquivo ./index.php é o ponto de partida da applicação.

A baixo tu podes ver o seu conteudo inicial

```php
<?php
    require __DIR__.'/jc/index.php';

    use jc\Jc;

    $app = new Jc();

    $app->run();
```

E pronto,  agora tu tens um app criado com jc, com apenas 4 linhas de codigo. Ao abrir seu navegador na porta em que o seu servidor está rodando, tu veras a pagina de boas vindas do JC.

## Paginas

para adicionar paginas no nosso app, basta chamar os metodos get, post, delete, put e route do nosso objecto `Jc`

```php
<?php
    require __DIR__.'/jc/index.php';

    use jc\Jc;

    use jc\response\Response;
    use jc\response\Render;
    use jc\request\Request;
    use jc\response\RedirectResponse;

    use function jc\url_for;

    $app = new Jc();

    $app->get('/', ['name' => 'home'], function(Request $request) {
        return new Response('HOME');
    });

    $app->get('/prophile/{username}', ['name' => 'prophile'], function(Request $request) {
        $username = $request['GET']['username'];

        return new Response("$username's prophile");
    });

    $app->route('/login', ['name' => 'login', 'methods' => ['GET', 'POST']], function(Request $request) {
        if ($request['METHOD'] == 'GET') return new Render('login');

        $username = $request['POST']['username'];
        $password = $request['POST']['password'];

        if ($username == 'user' && $password == '12345') return new RedirectResponse(url_for('home'));

        return new Render('login');
    });

    $app->run();
```

No codigo acima adicionamos tres paginas ao nosso app. A primeira usamos o metodo get para adicionar uma pagina que só podera ser acessada pelo metodo GET. O metodo usado para adicionar a pagina recebe tres argumentos.

- O caminho da pagina, (`'/'`)
- Um conjunto de configurações da pagina, como status code, name, middlewares, etc, (`['name' => 'home']`)
- Uma função que representa a pagina em si, ela recebe o parametro `$request`, um array que contem as informações da requisição, como METHOD, POST e GET (valores enviados pelo cliente usando os repetivos metodos), COOKIE, etc.

Repara que cada pagina retorna sempre um tipo Response ou um tipo derivado dele.

### Responses

Toda pagina deve retorna uma valor do tipo Response ou derivado.

Todas as responses tem a mesma assinatura.

```php
new Response('valor a ser retornado', $statuscode, $headers, $cookies);
```

Os parametros `headers` e `cookies` são arrays do tipo chave valor.

#### `Response`

este tipo de response é usado para retornar códigos basico de html em forma de string pura.

```php
new Response('<h1>Olá, Mundo!<h1/>');
```

#### `RedirectResponse`

este tipo de response é usado para redirecionar a requisição para uma outra pagina.

```php
new RedirectResponse('http://localhost:80/');
```

se a pagina tiver um nome definido tu podes usar a função url_for para simplificar as coisas.

```php
new RedirectResponse(url_for('nomedapagina'));
```

#### `JSONResponse`

este tipo de response é usado para retornar dados json, ele é usado na criação de api. 

```php
new JSONResponse([
    'name'     => 'user',
    'is_admin' => true
]);
```

#### `FILEResponse`

este tipo de response é usado para retornar arquivos, o seu primeiro argumento é o caminho completo do arquivo.

```php
new FILEResponse('./files/image.jpg');
```

#### `StreamingResponse`

este tipo de response é usado para retornar dados em partes para evitar a sobrecarga ou até mesmo no caso de que os dados ainda estejam em produção

```php
new StreamingResponse(function() {
    foreach ([1, 2, 3, 4] as $number) {
        yield $number;

        sleep(1) // Simulação de um processamento
    }
});
```

no exemplo acima os numeros serão enviados de cada vez para o cliente, e no fim de tudo a conexão sera desfeita

#### `Render`

este tipo de response é usado para retornar código html mais complexo, este código é escrito em um arquivo html separado. o seu primeiro argumento é o nome do arquivo html sem a sua extensão, estes arquivos são armazenados por padrão dentro do directorio `./templates/`, mas isso pode ser alterado ao definir o seu app, `$app = new Jc('static/', 'templates/');`, o primeiro argumento representa o directorio onde os arquivos estaticos são armazenados e o segundo argumento representa o directorio onde são armazenados os templates usados pelo response Render.

OBS. se os parametros não forem definidos eles tomam valorez padrão, `static/` e `templates/`.

```php
new Render('index');
```

Dentro do arquivo html o template, é possivel usar codigo php para tornalos dinamico. Basta adicionar o `'@'` no inicio da linha
que ela será interpretada como php. todo conteudo delimitado por `'{}'`.

OBS. use `'@@'` para que uma linha comece com `'@'` sem ser interpretado como php

veja abaixo o codigo de um template.

```html
<!DOCTYPE html>
<html>
    <head>
        <meta charset = "UTF-8">
        <meta name = "viewport" content = "width=device-width, initial-scale=1.0">
        <title>home</title>
        <link rel="stylesheet" href = "{{ $static("css/style.css") }}">
    </head>

    <body>
        {{ $include("Header") }}
        
        <nav>
            <ul>
                @foreach (["home", "login"] as $value) {
                    <li><a href = {{ $url($value) }}>{{ $value }}</a></li>
                @}
            </ul>
        </nav>

        @if ($username) {
            Olá, {{ $username }}
        @} else {
            Bem Vindo
        }
    </body>
</html>
```

No código acima são usados tres funções predefinidas dos templates.

- `$static`. retorna o caminho completo de um arquivo estático
- `$include`. retorna o conteudo de um outro template
- `$url`. retorna a url da pagina especificada no parametro, ela funciona como a função url_for.

E por fim, vimos também o identificador `$username`, este indentificador não é predefinida, ele precisa ser definido ao chamar o Render.

```php
new Render('index', [
    'username' => 'user'
]);
```

O Render é o unico response que o seu segundo argumento não é o statuscode mas sim um array usado para definir identificadores a serem usados no template. o statuscode fica sendo o terceiro argumento.

Existe uma forma de definir identificadores que faz com que eles sejam globais, ou seja poderam ser acessados em qualquer template, como é o caso dos identificadores predefinidos.

```php
Render::add_var('identificadorglobal', 'valor');
```

Tambem é possivel criar templates base e extendelos para outros templates. a baixo um exemplo de um template base.

```html
<!DOCTYPE html>
<html>
    <head>
        <meta charset = "UTF-8">
        <meta name = "viewport" content = "width=device-width, initial-scale=1.0">
        <title>{{ $block("title") }}</title>
        <link rel="stylesheet" href = "{{ $static("css/style.css") }}">
    </head>

    <body>
        {{ $include("Header") }}
        
        {{ $block("content") }}
    </body>
</html>
```

Todo lugar onde declaramos `{{ $block("contnet") }}` é usado para definir um bloco chamado content que sera utlizado o template que herdara deste template base. a baixo um exemplo de um template que herda da base.

```html
@$extends('base');

<Title>home</Title>

<Content>
    <nav>
        <ul>
            @foreach (["home", "login"] as $value) {
                <li><a href = {{ $url($value) }}>{{ $value }}</a></li>
            @}
        </ul>
    </nav>

    @if ($username) {
        Olá, {{ $username }}
    @} else {
        Bem Vindo
    }
</Content>
```

Como podes ver os blocos deinidos na base foram usados como tag html, observer que as primeiras letras estão a maiusculo para os diferenciar das tags originais.

### Páginas predifinidos

Existem algumas paginas ja predifinidas para você, como a pagina de login.

```php
$web->route('/auth/login', ['methods' => ['GET', 'POST'], 'name' => 'login'], Pages::login('login'));
$web->get('/auth/logout', ['name' => 'logout'], Pages::logout('login'));

$api->route('/token', ['methods' => ['POST', 'DELETE']], Pages::token());
$api->get('/token/restart/{token_restart}', [], Pages::restart_token());
```

### Upload de arquivos

O argumento `$requests` que é passado como parametro nos endpoints contem o metdo `get_files` que retorna um os ficheiros enviados na requisição, abaixo um exemplo de como salvar os arquivos, mas para isso deve-se definir a variavel de ambiente UPLOADFILE que deve ser o caminho da pasta onde serão salvos os arquivos.

```php
$web->route('/upload', ['name' => 'upload', 'methods' => ["GET", "POST"]], function(Request $request) {
      if ($request->method === "POST") {
         foreach ($request->get_files() as $key => $file) {
            $file->save();
         }
      }
      
      return new Render('upload');
   });
```

Opcionalmente o metodo save recebe um paramento indicando o camiho absoluto onde o arquivo deve ser salvo.
Alem do metodo save também temos o metdo storage que recebe como parametro o caminho onde queremos salvar o nosso arquivo mas dentro da pasta definida em UPLOADFILE,
temos o metodo set_name para alterar apenas o nome do arquivo, e por ultimo temos o metodo get_name.

## Quase WebSocket

Quem não tem cão, caça como gato :) :).

Em php puro infelismente não temos as maravilhas do que é websocket, criamos uma implementação de streaming que tenta ao maximo imitar o comportamento do websockt

### RealTime

O RealTime é um subtipo de Request que traz consigo um conjunto de metodos que facilitam a impletação do real_time (quase websockt)


```php
<?php
    require __DIR__.'/jc/index.php';

    use jc\Jc;

    use jc\request\RealTime;

    $app = new Jc();

    $app->real_time('/push', function(RealTime $real_time) {
        $real_time->wait_accept(); // Espera aceitar a conexão

        while (true) {
            $message = $real_time->wait_receive_json(); // Espera o cliente mandar uma mensagem, outro metodo poderia ser o wait_receive.

            if ($message === null) // Se a conexão for disfeita
                return new Response("");

            $connections = $real_time->get_connections(); // Obtem um array com todos as conexões presente
            $real_time->wait_send_json($message, $connections); // Envia a mensagem vinda do cliente para todas as connecxões
        }
    });
```

Veja um exemplo de um codigo javascript a servir de cliente

```javascript
const name = "Author"

let connection = null

const url = `https://localhost/local/push`

const event_source = new EventSource(url)

event_source.onmessage = event => {
    if (event.data == "Are you ok") return // O servidor envia esta mensagem constatimente para verifcar a conexão
    
    if (!connection) { // A primeira resposta do servidor é o id da conexão
        connection = event.data
        return
    }
    
    const message = event.data // mensagem vinda do servidor
}

const send_message = async () => {
    await fetch(url, {
        method: 'POST',
        body: JSON.stringfy({
            connection,
            message: "My message"
        })
    })
}
```

## Modularizando o app

A medida que mais paginas são adicionados ao app, mais longo fica o arquivo `./index.php`, e isto não é muito legal e torna a manutenção do codigo demasiadamente complexa.

O Jc permite devidir o seu app em modulos, por exemplo tu podes ter um modulo voltado apenas para paginas web e outro modulo voltado para api.

criaremos um directorio na raiz do projecto. `./routes/`, e dento deste directorio criaremos tres arquivos, `index.php`, `api.php`, `web.api`.

```bush
.
│
├── routes
│   ├── api.php
│   ├── index.php
│   └── web.php
```

No arquivo ./routes/web.php

```php
<?php
    use jc\JCRoute;
    use jc\response\Render;

    $web = new JCRoute();

    $web->get('/', ['name' => 'home'], function($request) {
        return new Render('home');
    });

    $web->get('/prophile/{username}', ['name' => 'prophile'], function($request) {
        $username = $request['GET']['username'];

        return new Render('prophile');
    });

    return $web;
```

Tu deves ter reparado no novo tipo, o `JCRoute`, ele é como o `Jc`, a diferença que há entre eles é que o `Jc` tem mais funcionalidades e cada projecto tera um unico `Jc` definido, ele é a raiz do projecto, ele é o unico que contem o metodo `run`, usado para inicializar o projecto.

No arquivo ./routes/api.php

```php
<?php
    use jc\JCRoute;
    use jc\response\JSONResponse;

    $api = new JCRoute();

    $api->get('/{userid}', ['name' => 'user'], function($request) {
        $users = [
            ['nome' => 'user', 'email' => 'user@example.com'],
            ['nome' => 'user1', 'email' => 'user1@example.com']
        ];
        
        $userid = $request['GET']['userid'];

        if (isset($users[$userid]))
            return new JSONResponse($users[$userid]);

        return new JSONResponse([
            'detail' => 'usuário não encontrado'
        ], 404);
    });

    $api->get('/', ['name' => 'users'], function($request) {
        return new JSONResonse([
            ['nome' => 'user', 'email' => 'user@example.com'],
            ['nome' => 'user1', 'email' => 'user1@example.com']
        ]);
    });

    $api->post('/', ['name' => 'insertuser', 'response_code' => 201], function($request) {
        if (isset($request['POST']['nome']) && isset($request['POST']['email']))
            return new JSONResponse([
                'detail' => 'usuário criado com sucesso',
                'user'   => [
                    'nome'  => $request['POST']['nome'],
                    'email' => $request['POST']['email']
                ]
            ]);

        return new JSONResponse([
            'detail' => 'dados em falta'
        ], 400);
    });

    return $api;
```

No arquivo ./routes/index.php

```php
<?php
    $api = require_once __DIR__.'/api.php';
    $web = require_once __DIR__.'/web.php';

    use jc\JCRoute;

    $routes = new JCRoute();

    $routes->include_route($api, '/api');
    $routes->include_route($web);

    return $routes;
```

Aqui estamos perante um metodo novo, o metodo include_route é usado para incluir asrotas de um app em outro app, neste caso as rotas dos apps `$web` e `$api` estão sendo incluindo no app `$routes`.

O primeiro argumento deste metodo é o app em si, e o segundo é o prefixo que sera adicionados em todas as urls das paginas desse mesmo app.

Uma vez que os apps `$web` e `$api` foram incluidos no app `$routes`, devemos incluir o este mesmo app no app principal.

Arquivo ./index.php

```php
<?php
    require __DIR__.'/jc/index.php';
    
    $routes = require_once __DIR__.'/routes/index.php';

    use jc\Jc;

    $app = new Jc();

    $app->include_route($routes);

    $app->run();
```

## Construtor de consultas

Um construtor de consultas o Query Builder é uma ferramenta que te possiblita interagir com banco de dados sem a necessidade de usar código sql diretamente.

O Jc traz consigo um Query Builder muito simples de ser usado.

```php
<?php
    use jc\JCRoute;
    use jc\response\JSONResponse;
    use jc\qbuilder\QBuilder;
    use jc\util\Util;

    use function jc\qbuilder\q;

    $api = new JCRoute();

    $api->get('/{userid}', ['name' => 'user'], function($request) {
        $db = db();

        $userid = $request['GET']['userid'];

        $user = $db->table('user')
            ->select()
            ->where(q()->id->equal($userid))
            ->query()
            ->first();

        if ($user)
            return new JSONResponse($user);

        return new JSONResponse([
            'detail' => 'usuário não encontrado'
        ], 404);
    });

    $api->get('/', ['name' => 'users'], function($request) {
        $db = db();

        $users = $db->table('user')
            ->select()
            ->query()
            ->all();

        return new JSONResponse($users);
    });

    $api->delete('/{userid}', ['name' => 'deleteuser'], function($request) {
        $db = db();

        $userid = $request['GET']['userid'];

        $user = $db->table('user')
            ->select()
            ->where(q()->id->equal($userid))
            ->query()
            ->first();

        if ($user) {
            $db->table('user')
                ->delete()
                ->where(q()->id->equal($userid))
                ->execute();

                return new JSONResponse([
                    'detail' => 'usuário deletado com sucesso',
                    'user'   => $user
                ]);
        }

        return new JSONResponse([
            'detail' => 'usuário não encontrado'
        ], 404);
    });

    $api->put('/{userid}', ['name' => 'updateuser'], function($request) {
        $db = db();

        $userid = $request['GET']['userid'];

        $userexist = $db->table('user')
            ->select()
            ->where(q()->id->equal($userid))
            ->query()
            ->exist();

        if ($userexist) {
            $is_valid = Util::is_valid($request['POST'], [
                'username'  => 'string|required',
                'email'     => 'is_email|required',
                'passwordd' => 'string|required|minlength-8'
            ]);

            if ($is_valid[0]) {
                $db->table('user')
                    ->update($request['POST'])
                    ->where(q()->id->equal($userid))
                    ->execute();

                $user = $db->table('user')
                    ->select()
                    ->where(q()->id->equal($userid))
                    ->query()
                    ->first();

                return new JSONResponse([
                    'detail' => 'usuario atualizado com sucesso',
                    'user'   => $user
                ]);
            }

            return new JSONResponse($is_valid[1], 400);
        }

        return new JSONResponse([
            'detail' => 'usuário não encontrado'
        ], 404);
    });

    $api->post('/', ['name' => 'insertuser'], function($request) {
        $db = db();

        $is_valid = Util::is_valid($request['POST'], [
            'username'  => 'string|required',
            'email'     => 'is_email|required',
            'passwordd' => 'string|required|minlength-8'
        ]);

        if ($is_valid[0]) {
            $db->table('user')
                ->insert($request['POST'])
                ->execute();

            $user = $db->table('user')
                ->select()
                ->where('id = max(id) no sql injection') // Se usuares codigo sql diretamente,deves informar que ele não é vulneravel a sqlinject
                ->query()
                ->first();

            unset($user['max(id)']);

            return new JSONResponse([
                'detail' => 'usuario inserido com sucesso',
                'user'   => $user
            ]);
        }

        return new JSONResponse($is_valid[1], 400);
    });
```

O `QBuilder` é o construtor de consultas do JC, ele é simples e facil de compreender, ao ser inicializado o primeiro parametro indica o tipo de base de dados a ser usado, `mysql` e `sqlite`, no momento ele suporta apenas essas duas opções, o segundo parametro é o nome da base de dados ou o caminho dele, no caso do sqlite, desde o terceiro parametro ao ultimo são aplicaveis apenas quando se trata de uma base de dados do tipo `mysql`, o terceiro parametro é a palvra passe do banco de dados, o quarto parametro é o host do banco de dados, o quinto parametro é o usuário do banco de dados e o sexto parametro é a porta do banco de dados.

A maioria dos metodos do `QBuilder` retornam a si proprio.

Ele contem apenas 3 metodos que não retornam a si proprio

- `first`. use este metodo para pegar o primeiro elemento da lista de resultados de uma busca
- `all`. use este metodo para pegar todos os elementos da lista de resultados de uma busca
- `exist`. use este metodo para verificar se uma busca teve resultado

Ele tem os metodos

- `table`, para definir a tabela
- `select`, para consultar dados na tabela
- `delete`, para deletar dados na tabela
- `update`, para atualizar dados na tabela
- `insert`, para inserir dados na tabela
- `where`, para filtrar as linhas a serem afetadas
- `and_where`, para filtrar as linhas a serem afetadas, ele só é aplicado depois de um `where`, ele concatenara o `where` passado com ele mesmo adicionando o operador `and` no centro
- `or_where`, para filtrar as linhas a serem afetadas, ele só é aplicado depois de um `where`, ele concatenara o `where` passado com ele mesmo adicionando o operador `or` no centro
- `execute`, para executar a sua query depois dela ser construida, este metodo não retorna nenhum valor, use ele quando não precisar do valor renortado por uma consulta
- `query`, para executar a sua query depois dela ser construida, este metodo retorna o resultado da busca

Ele também tras consigo um metodo estatico, o metodo `prepare` é usado para se previnir de ataques do tipo injecção de sql, ele recebe como parametro um valor string e rotorna o mesmo valor, porem bem tratado e eliminado qualquer sinal de vulnerablidade.

No código acima, também apareceu o tipo `Util`, ele é um conjuto de metodos estaticos que adicionam ao Jc, um conjunto de funções praticas e convenientes.

Neste caso usamo o metodo `is_valid`, para validar os valores oriundos do usuário, seu primeiro parametro é um array que contem os tais valores a serem validados, por exemplo, `['nome' => 'user']`, e o segundo parametro é um array com as condições para validar cada campo, por exemplo `'['name' => 'string|required']'`, neste exemplo o metodo só retornara `true` se o campo `name` for do tipo string, e estiver presente, as condições são separadas pelo `|`.

Veja a baixo a lista de condições.

- `string`. O campo precisa ser do tipo `string`
- `int`. O campo precisa ser do tipo `int`
- `float`. O campo precisa ser do tipo `float`
- `bool`. O campo precisa ser do tipo `bool`
- `enum-(value1, value2...)`. O campo precisa ser igual a um dos valores especificados no `enum`
- `is_email`. O campo precisa ser um email
- `required`. O campo é obrigatorio
- `blank`. O campo pode ser igual a `''`
- `length-x`. O campo precisa ter um comprimento igual a x, sendo x qualquer valor inteiro
- `minlength-x`. O campo precisa ter um comprimento de no minimo x, sendo x qualquer valor inteiro
- `maxlength-x`. O campo precisa ter um comprimento de no maximo x, sendo x qualquer valor inteiro

## Modelos

Use modelos para mapear dados em Objetos. exemplo a baixo.

```php
<?php
    use jc\model\Model;

    class Enterprise extends Model {
        protected static ?string $table_name = "enterprise";

        # Definindos os atributos do modelo
        public ?int $id;
        public string $name;
    }

    $db = db();

    $enterprise = new Enterprise("GTecnology");
    $enterprise->save($db);

    $enterprise->name = "Novo nome";
    $enterprise->save($db);
    $nterprise->delete($db);

    $enterprise = Enterprise::get(1);

    if (!$enterprise) {

    }


```

## Middlewares

Middlewares são ações executadas antes e/ou depois de uma pagina.

A baixo um código que cria um middleware

```php
<?php
    function middleware($view) {
        return function($request) use ($view) {

            // execute alguma coisa antes da view

            $response = $view($request);

            // execute alguma coisa depois da view

            return $response;
        };
    }
```

O codigo acima cria um middleware, ele pode ser adicionado a um a todas a paginas de um app ou a paginas especificas.

```php
<?php
    use jc\Jc;
    use jc\response\Response;

    $app = new Jc();
    
    // aplicando o middleare a todas as paginas deste app
    $app->add_middleware(middleware);

    // aplicando o middleware a uma unica pagina
    $app->get('/', ['middlewares' => [middleware]], function($request) {
        return new Response('home');
    });
```

### Middlewares predefinidos

O Jc traz consigo um conjunto de middlewares predefinidos e tornaram o desenvolvimento da aplicação mais simples.

#### cors

Este é um middleware usado para definir quais host podem interagir com a sua app.

```php
<?php
    use jc\Jc;
    
    $app = new Jc();

    $app->add_middleware(Middleware::cors([
        'allow-origin' => 'http://localhost:5317'
    ]));

    $app->run();
```

**Para usar os middlewares que vem a baixo**

tu deves definir as informações da sua base de dados no arquivo de configuração(.env).

```properties
DATABASE=mysql
DATABASENAME=jc
DATABASEPASSWORD=
DATABASEHOST=localhost
DATABASEUSER=root
DATABASEPORT=3306
```

também é necessario definir algumas tabelas na sua base de dados, Falaremos mais sobre tais tabelas na sessão `Migrations`.

#### csrftoken

Este é um middleware usado para dar mais segurança aos formularios web.

```php
<?php
    use jc\JCRoute;
    
    $web = new JCRoute();

    $web->add_middleware(Middleware::csrftoken());

    return $web;
```

Este middleware cria um identificador global para ser usado nos templates, este identificador é o `$csrftoken`, todos os formulários com o metodo diferente de GET devem conter o seguinte conteudo: `{{ $csrftoken() }}`

#### authuser

Este middleware analiza os dados enviados pelo usuario e tenta autenticar o usuário com os mesmos dados, ele cria o identificador global `$user` para ser usado nos templates.

#### login_required

Este middleware é usado para restringir o acesso a uma pagina a pessoas logadas, ele deve ser utilizado em conjunto com o middleware `authuser`.

## Processamento em segundo plano

Em algum momento tu podes precisar de processar alguma informação em sua aplicação, para resolver isto basta cria uma pasta que contera todos os arquivos php a serem executados em segundo plano, seus nomes devem sempre começar com `func_`, apos ter feito isso basta rodar o seguinte comando.

```bash
php jc/backgroundplain.php /suapasta
```

## Cashe

Voltando a falar sobre o QBuilder, tu podes usar cashe ao fazer uma consulta.

```php
$db->use_cashe(60);
```

O método use_cashe recebe como parametro um numero que representa o tempo em segundos que o cashe será valido.

Sempre que tu usares cashe, deves te lembrar de criares um processo em segundo plano para limpar o cashe de tempo em tempo. basta criar um arquivo na sua pasta de processos e colocar o conteudo seguinte.

```php
<?php

    require 'jc/util/util.php';

    use jc\util\Util;

    Util::clean_cashe();
```

## Queue

É possivel usar as Queues para fazer processos diferentes se comunicarem, diminuir a carga do servidor fazendo com que alguns serviços fiquem esperando na Queue, abaixo exemplo de um endpoint que manda informações na queue a cada requisição

```php
use jc\queue\Queue;

$queue = new Queue("id da queue");
$queue->push(json_encode($request->data()));
```

depois disso você deve criar um processo em segundo plano para consumir a queue, exemplo abaixo

```php
<?php

    require 'jc/qbuilder/qbuilder.php';
    require 'jc/helpers.php';
    require 'jc/queue/queue.php';

    use jc\queue\Queue;

    up_env();

    (new Queue("id da queue"))->consumer(function (array $items) {
        $db = db();

        foreach ($items as $item) {
            $db->table("uuid")->insert(json_decode($item, true))->save_sql();
        }

        $db->execute_all();

        $db->close();
    }, 6);
```

## CQRS

Use duas base de dados para segregar as responsabilidades de escrita e leitura, tu deves definir a variavel de ambiente cqrs como true.

ao usares cqrs deves criar um processo em segundo plano que deve sincronizar as bases de dados, abaixo o exemplo de arquivo.

```php
<?php

    require 'jc/helpers.php';
    require 'jc/queue/queue.php';
    require 'jc/util/util.php';
    require 'jc/qbuilder/qbuilder.php';

    use jc\util\Util;

    Util::sync_db();
```

## Migrations

Aqui você escreve as usas proprias migrations, mas não e sql e sim em php. abaixo tem o exemplo de um arquivo de migration

```php
<?php

    require __DIR__."/../jc/migration.php";

    use jc\qbuilder\QBuilder;
                        
    $migrations = [
        "create user" => [
            fn(QBuilder $db) => $db->table("user")->create([...SCHEMA_USER])
        ],

        "Configure Token" => [
            fn(QBuilder $db) => $db->table("token")->create([...SCHEMA_TOKEN]),

            fn(QBuilder $db) => $db->table("token_restart")->create([...SCHEMA_TOKEN_RESTART])
        ],

        "Create table publich" => [
            fn(QBuilder $db) => $db->table("publich")->create([
                "id"      => [integer_(), primary_key(), not_null(), auto_increment()],
                "content" => [text(), not_null()],
                "user"    => [integer_(), foreign_key(), not_null(), "reference" => "user(id)", "on_delete" => "CASCADE"]
            ])
        ],

        "Rename table publich => post" => [
            fn(QBuilder $db) => $db->table('publich')->rename('post')
        ],

        "Add column date to table post" => [
            fn(QBuilder $db) => $db->table('post')->add_column('date', [date_(), not_null(), "default" => "CURRENT_TIMESTAMP"])
        ],

        "Change column date => created_at to table post" => [
            fn(QBuilder $db) => $db->table('post')->change_column('date', [date_(), not_null(), "default" => "CURRENT_TIMESTAMP"], 'created_at')
        ],

        "Drop column created_at from table post" => [
            fn(QBuilder $db) => $db->table('post')->drop_column('created_at')
        ],

        "Drop table post" => [
            fn(QBuilder $db) => $db->table("post")->drop()
        ],

        "Create table uuid" => [
            fn(QBuilder $db) => $db->table("uuid")->create([
                "id"      => [varchar(15), primary_key(), not_null()],
                "content" => [varchar(100), not_null()]
            ])
        ]
    ];

    run_migration($migrations);

```

A parte principal do arquivo acima é a funcão `run_migration` que recebe como parametro um array associativo contendo as suas migrações, onde a chave indica o nome da migração e o valor é um array onde cada elemento é uma função que executa uma instrução na base de dados, tu deves ter reparado nos novos metodos da classe QBuilder, como o create usado para criar tabelas, ele recebe como parametro um array associativo onde as chaves representam um atributo da tabela e o valor um array contendo as especificações de cada produto, por exemplo `(["id" => [integer_(), not_null(), primary_key(), auto_increment()]])`, a ordem não importa, desde que o tipo seja o primeiro item.

Lembra quando falamos que são necessárias algumas tabelas para que algums middlewares funcionem?, bem essas tabelas são as tabelas de usuário, token e restart_token, a estrutura delas já está definida em constantes e basta passalas como argumento no metodo create, ex: create([...SCHEMA_USER])

```bash
php file_migration.php
```

## Tests

Para poderes testar a sua aplicação é necessário definir a variavel de ambiente MIGRATION, o seu valor deve ser o caminho que aponta para o seu arquivo de migration.

Temos a classe de testes que você usara para testar suas aplicações, abaixo exemplo de um codigo de tests

```php
<?php

    require __DIR__.'/../jc/test.php';

    Request::set_default_headers([
        "Content-Type" => "application/json"
    ]);
    
    Request::set_base_uri("http://localhost/jc");

    
    class TestCaseUser extends TestCase {
        public string $user_email = "albinondjonale@gmail.com";
        public int $user_id;
        
        public function set_up() {
            $this->user_email = "albinondjonale@gmail.com";

            $response_create_user = Request::post("/api/users", [
                "username" => "Albino Ndjonale",
                "email"    => $this->user_email,
                "password" => "123456789",
                "is_admin" => 1
            ]);

            $response_user = Request::get($response_create_user->headers["location"]);
            $this->user_id = $response_user->json()["id"];
        }

        public function start() {
            
            $this->add_test('create user', function() {
                $response = Request::post("/api/users", [
                    "username" => "Albino Ndjonale",
                    "email"    => "albinondjonale1@gmail.com",
                    "password" => "123456789",
                    "is_admin" => 0
                ]);

                assert_equal($response->status_code, 201);
            });

            $this->add_test('create user with bad request', function() {
                $response = Request::post("/api/users", [
                    "username" => "Albino Ndjonale",
                    "email"    => "albinondjonale2@gmail.com",
                    "is_admin" => 0
                ]);

                assert_equal($response->status_code, 400);
            });

            $this->add_test('create user with email duplicated', function() {
                $response = Request::post("/api/users", [
                    "username" => "Albino Ndjonale",
                    "email"    => $this->user_email,
                    "password" => "123456789",
                    "is_admin" => 0
                ]);

                assert_equal($response->status_code, 400);
            });

            $this->add_test('list users', function() {
                $response = Request::get("/api/users");
                
                assert_equal($response->status_code, 200);
            });

            $this->add_test('get user', function() {
                $response = Request::get("/api/users/{$this->user_id}");
                
                assert_equal($response->status_code, 200);
            });

            $this->add_test('get user not exist', function() {
                $response = Request::get("/api/users/1000");
                
                assert_equal($response->status_code, 404);
            });
        }
    }

    new TestCaseUser("Test User");

    TestCase::run();

```
