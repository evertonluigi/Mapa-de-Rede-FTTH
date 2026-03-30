# FTTH Network Manager

Sistema web completo para gerenciamento de redes de fibra óptica (FTTH/GPON), desenvolvido em PHP + MariaDB. Permite mapear toda a infraestrutura — postes, cabos, CTOs, CEOs, splitters, OLTs — com visualização geográfica interativa, gestão de clientes, mapa de fusões e dashboard de KPIs em tempo real.

---

## Funcionalidades

### Mapa Interativo (Leaflet.js)
- Visualização geográfica de toda a rede em tempo real
- Postes, cabos, CEOs, CTOs, OLTs e clientes plotados no mapa
- Traçado de rota de fibra com cálculo de atenuação óptica
- Popups detalhados com informações de cada elemento
- Filtros por tipo de elemento e status

### CTOs — Caixas Terminais Ópticas
- Cadastro com capacidade de portas, fabricante, modelo
- Vinculação a poste e OLT PON (Slot/PON)
- Detecção automática de Slot/PON via rastreamento da topologia de fusões
- Visualização de ocupação de portas (barra de progresso)
- QR Code para acesso rápido em campo

### CEOs — Caixas de Emenda Óptica
- Cadastro com capacidade de fibras
- Mapa de Fusão visual (editor drag-and-drop por bandeja)
- Suporte a fusões tipo: emenda, passante e splitter
- Registro de perda por fusão (dB)

### Cabos Ópticos
- Cadastro com número de fibras, tipo (drop, monomodo, etc.)
- Cores de fibras configuráveis por tubo
- Pontos de passagem pelo mapa (polilinha)
- Registros de reserva de metragem

### Splitters
- Cadastro com relação (1:4, 1:8, 1:16, 1:32)
- Perda de inserção configurável
- Vinculação a CEOs e CTOs (atendimento ou derivação)

### OLTs e PONs
- Cadastro de OLTs com IP de gerência, rack, localização
- Cadastro de PONs por Slot/número com potência de saída (dBm)
- Rastreamento automático: qual PON alimenta cada CTO/cliente

### Clientes
- Cadastro completo (CPF/CNPJ, contrato, ONU, plano)
- Vinculação a CTO e porta específica
- Medição de sinal óptico (dBm) com indicador visual de qualidade
- Exibição do Slot/PON da OLT que atende o cliente

### Dashboard KPIs (Tempo Real)
- % de CTOs com ocupação acima de 80%
- Clientes com sinal crítico (< -27 dBm)
- Manutenções abertas por prioridade
- Crescimento mensal de clientes (gráfico de barras)
- Atualização automática a cada 2 minutos

### Manutenções
- Registro por elemento (poste, cabo, CEO, CTO, cliente, etc.)
- Tipos: corte, fusão, substituição, medição, visita, outros
- Prioridades: baixa, média, alta, crítica
- Histórico por elemento e dashboard consolidado

### Administração
- Controle de usuários com perfis (admin, técnico, visualizador)
- Audit Log: registro de todas as ações (criar, editar, deletar)
- Logs de acesso por usuário e IP
- Configurações gerais da aplicação
- Upload de fotos por elemento

### QR Code
- Geração de QR Code para qualquer elemento da rede
- Acesso direto ao detalhamento em campo pelo celular

---

## Requisitos

| Componente | Versão mínima |
|---|---|
| PHP | 8.1+ |
| MariaDB / MySQL | 10.6+ / 8.0+ |
| Servidor web | Apache 2.4 / Nginx |
| Extensões PHP | `pdo_mysql`, `gd` ou `imagick`, `mbstring`, `json`, `session` |

---

## Instalação

### 1. Clonar ou fazer upload dos arquivos

```bash
git clone https://github.com/seu-usuario/ftth-network-manager.git /var/www/html/mapas
```

Ou faça upload do ZIP via FTP/File Manager para a pasta desejada no servidor.

### 2. Configurar permissões

```bash
chmod -R 755 /var/www/html/mapas
chmod -R 777 /var/www/html/mapas/uploads
```

### 3. Instalar via Setup Web (recomendado)

Acesse no navegador:

```
https://seudominio.com.br/mapas/setup.php
```

Preencha:
- **Host / Porta / Nome do banco**: dados do MySQL no seu servidor
- **Usuário e senha MySQL**: com permissão para criar banco e tabelas
- **Administrador**: nome, e-mail e senha do usuário admin
- **URL base**: URL raiz da instalação (sem barra final)

Clique em **Instalar Banco de Dados**. O setup cria:
- 24 tabelas com todas as relações
- Perfis padrão (admin, técnico, visualizador)
- Usuário administrador
- Configurações padrão
- Arquivo `config/config.php` automaticamente

> **Após a instalação, delete o arquivo `setup.php` do servidor!**

### 4. Configuração manual (alternativa ao setup)

Crie o arquivo `config/config.php`:

```php
<?php
define('DB_HOST',   'localhost');
define('DB_PORT',   '3306');
define('DB_NAME',   'ftth_network');
define('DB_USER',   'seu_usuario');
define('DB_PASS',   'sua_senha');
define('BASE_URL',  'https://seudominio.com.br/mapas');
```

Importe o arquivo SQL no banco:

```bash
mysql -u seu_usuario -p ftth_network < ftth_hostinger.sql
```

### 5. Configurar o servidor web

**Apache** — certifique-se que `mod_rewrite` está ativo. O `.htaccess` já está incluído no projeto.

**Nginx** — exemplo de bloco:

```nginx
location /mapas {
    root /var/www/html;
    index index.php;
    try_files $uri $uri/ /mapas/index.php?$query_string;
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### 6. Instalação na Hostinger (Shared Hosting)

1. Acesse o **File Manager** do hPanel
2. Faça upload de todos os arquivos para `public_html/mapas/` (ou subpasta desejada)
3. No **phpMyAdmin**, crie um banco de dados e importe `ftth_hostinger.sql`
4. Crie `config/config.php` com os dados fornecidos pela Hostinger
5. Acesse `https://seudominio.com.br/mapas` e faça login

---

## Estrutura de Diretórios

```
mapas/
├── api/                  # Endpoints JSON (elementos, KPIs, sinal, etc.)
├── assets/
│   ├── css/              # Estilos globais
│   └── js/               # map.js e scripts frontend
├── config/
│   └── config.php        # Configurações de banco e URL
├── includes/             # Header, footer, helpers, classes PHP
├── modules/
│   ├── cabos/
│   ├── ceos/
│   ├── clientes/
│   ├── ctos/
│   ├── fusoes/
│   ├── kpis/
│   ├── manutencoes/
│   ├── olts/
│   ├── postes/
│   ├── qrcode/
│   ├── racks/
│   ├── splitters/
│   └── usuarios/
├── uploads/              # Fotos (criada automaticamente)
├── dashboard.php         # Mapa principal
├── index.php             # Redireciona para login/dashboard
├── login.php
├── setup.php             # Instalador web (deletar após uso)
└── README.md
```

---

## Acesso padrão após instalação

| Campo | Valor |
|---|---|
| URL | `https://seudominio.com.br/mapas` |
| Usuário | O informado no setup (padrão: `admin`) |
| Senha | A informada no setup |

---

## Segurança

- Senhas armazenadas com `password_hash()` (bcrypt)
- Todas as queries usam prepared statements (PDO)
- Audit log completo de ações
- Controle de sessão com timeout configurável
- Perfis de acesso com permissões granulares

---

## Licença

Este projeto é disponibilizado para uso livre. Sinta-se à vontade para adaptar às necessidades da sua empresa de telecom.

---

## Suporte

Abra uma [issue](../../issues) descrevendo o problema com o máximo de detalhes possível.
