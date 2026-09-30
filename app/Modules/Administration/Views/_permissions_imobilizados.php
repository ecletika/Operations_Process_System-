<?php
/**
 * Separador "Imobilizados" de Perfis & Permissões: que departamentos podem
 * ver o separador "Imobilizados (todos)" da Caixa de Entrada.
 *
 * Duas listas lado a lado em vez de uma lista de caixas para marcar. Numa
 * lista única, com dezasseis departamentos, ver quem tem acesso obriga a
 * percorrer tudo à procura dos que estão marcados. Aqui quem tem acesso está
 * de um lado e quem não tem está do outro — não há como enganar-se.
 *
 * Os itens são <label> com um checkbox escondido: clicar num item troca-o de
 * estado. O JavaScript passa-o logo para a outra coluna; sem JavaScript o
 * item fica onde está, mas marcado, e muda de coluna ao guardar. O
 * formulário submete o mesmo nos dois casos.
 *
 * @var array<int,array<string,mixed>> $departments
 * @var int[] $imobilizadosDepartmentIds
 */

$comAcesso = [];
$semAcesso = [];

foreach ($departments as $d) {
    $tem = in_array((int) $d['id'], $imobilizadosDepartmentIds, true);
    $d['etiqueta'] = ($d['branch_name'] ?? '') !== ''
        ? $d['branch_name'] . ' · ' . $d['name']
        : (string) $d['name'];

    if ($tem) {
        $comAcesso[] = $d;
    } else {
        $semAcesso[] = $d;
    }
}
?>

<style>
  .dep-colunas { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; align-items: start; }
  @media (max-width: 860px) { .dep-colunas { grid-template-columns: 1fr; } }

  .dep-coluna {
    background: var(--ops-white);
    border: 1px solid var(--ops-border);
    border-radius: 10px;
    overflow: hidden;
  }
  .dep-coluna.tem { border-color: #86c9a4; }

  .dep-cabecalho {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 11px 14px; border-bottom: 1px solid var(--ops-border);
  }
  .dep-coluna.tem .dep-cabecalho { background: #eefaf3; border-bottom-color: #c9e9d8; }
  .dep-coluna.nao .dep-cabecalho { background: #f8fafc; }

  .dep-titulo { font-weight: 600; font-size: 14px; color: #1f2937; display: flex; align-items: center; gap: 7px; }
  .dep-conta {
    background: #e5e7eb; color: #374151; border-radius: 999px; padding: 1px 9px;
    font-size: 12px; font-weight: 700; font-variant-numeric: tabular-nums;
  }
  .dep-coluna.tem .dep-conta { background: #146c43; color: #fff; }

  .dep-busca { padding: 9px 11px; border-bottom: 1px solid #f1f3f5; position: relative; }
  .dep-busca input {
    width: 100%; border: 1px solid var(--ops-border); border-radius: 8px;
    padding: 8px 10px 8px 32px; font-size: 13.5px; font-family: inherit; color: var(--ops-text);
  }
  .dep-busca input:focus { outline: none; border-color: var(--ops-primary); box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
  .dep-busca svg { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); width: 15px; height: 15px; color: #9ca3af; }

  .dep-lista { list-style: none; margin: 0; padding: 6px; max-height: 330px; overflow-y: auto; }
  .dep-lista li { margin: 0; }

  .dep-item {
    display: flex; align-items: center; gap: 9px;
    padding: 8px 10px; border-radius: 7px; cursor: pointer; font-size: 13.5px; color: #374151;
  }
  .dep-item:hover { background: #f3f4f6; }
  .dep-item input { position: absolute; opacity: 0; width: 1px; height: 1px; pointer-events: none; }
  .dep-item input:focus-visible + .dep-acao { outline: 2px solid var(--ops-primary); outline-offset: 2px; }

  .dep-acao {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 22px; border-radius: 6px; flex-shrink: 0;
    font-size: 15px; line-height: 1; font-weight: 600;
  }
  .dep-coluna.tem .dep-acao { background: #d8f0e3; color: #146c43; }
  .dep-coluna.nao .dep-acao { background: #eef1f4; color: #6b7280; }
  .dep-item:hover .dep-acao { filter: brightness(.94); }

  .dep-vazio { padding: 22px 14px; text-align: center; color: #9ca3af; font-size: 13px; }
  .dep-nada { padding: 14px; text-align: center; color: #9ca3af; font-size: 12.5px; display: none; }
</style>

<p style="color:#6b7280;font-size:13.5px;max-width:78ch">
  Escolha os departamentos cujos utilizadores podem abrir o separador
  <strong>«Imobilizados (todos)»</strong> na Caixa de Entrada. Essa lista mostra os carros
  parados de toda a casa e é <strong>apenas para consultar</strong> — quem a vê não passa a
  poder alterar processos de outras pessoas.
</p>

<form method="POST" action="/admin/permissions/imobilizados" id="form-imobilizados">
  <?= csrf_field() ?>

  <div class="dep-colunas" style="margin-top:14px">

    <div class="dep-coluna tem" data-coluna="tem">
      <div class="dep-cabecalho">
        <span class="dep-titulo">✅ Com acesso</span>
        <span class="dep-conta" data-conta><?= count($comAcesso) ?></span>
      </div>
      <ul class="dep-lista" data-lista>
        <?php foreach ($comAcesso as $d): ?>
          <li data-nome="<?= e(mb_strtolower((string) $d['etiqueta'])) ?>">
            <label class="dep-item">
              <input type="checkbox" name="departments[]" value="<?= (int) $d['id'] ?>" checked>
              <span class="dep-acao" aria-hidden="true">−</span>
              <span><?= e((string) $d['etiqueta']) ?></span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="dep-vazio" data-vazio<?= $comAcesso !== [] ? ' style="display:none"' : '' ?>>
        Nenhum departamento tem acesso.<br>Escolha-os na lista ao lado.
      </div>
      <div class="dep-nada" data-nada>Nenhum departamento com esse nome.</div>
    </div>

    <div class="dep-coluna nao" data-coluna="nao">
      <div class="dep-cabecalho">
        <span class="dep-titulo">🚫 Sem acesso</span>
        <span class="dep-conta" data-conta><?= count($semAcesso) ?></span>
      </div>
      <div class="dep-busca">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6">
          <circle cx="7" cy="7" r="4.5"/><path d="m10.5 10.5 3 3"/>
        </svg>
        <input type="text" id="dep-procurar" placeholder="Procurar departamento…" autocomplete="off">
      </div>
      <ul class="dep-lista" data-lista>
        <?php foreach ($semAcesso as $d): ?>
          <li data-nome="<?= e(mb_strtolower((string) $d['etiqueta'])) ?>">
            <label class="dep-item">
              <input type="checkbox" name="departments[]" value="<?= (int) $d['id'] ?>">
              <span class="dep-acao" aria-hidden="true">+</span>
              <span><?= e((string) $d['etiqueta']) ?></span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="dep-vazio" data-vazio<?= $semAcesso !== [] ? ' style="display:none"' : '' ?>>
        Todos os departamentos têm acesso.
      </div>
      <div class="dep-nada" data-nada>Nenhum departamento com esse nome.</div>
    </div>

  </div>

  <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
    <button type="submit" class="ops-btn" style="width:auto">Guardar acessos</button>
    <span style="color:#9ca3af;font-size:12.5px">Clique num departamento para o mudar de lado.</span>
  </div>
</form>

<script>
(function () {
  var form = document.getElementById('form-imobilizados');
  if (!form) { return; }

  var colunas = {
    tem: form.querySelector('[data-coluna="tem"]'),
    nao: form.querySelector('[data-coluna="nao"]')
  };
  var procurar = document.getElementById('dep-procurar');

  function atualizar(coluna) {
    var lista = coluna.querySelector('[data-lista]');
    var itens = Array.prototype.slice.call(lista.children);
    var visiveis = itens.filter(function (li) { return li.style.display !== 'none'; });

    coluna.querySelector('[data-conta]').textContent = itens.length;
    coluna.querySelector('[data-vazio]').style.display = itens.length === 0 ? '' : 'none';
    coluna.querySelector('[data-nada]').style.display =
      (itens.length > 0 && visiveis.length === 0) ? '' : 'none';
  }

  // Clicar num departamento passa-o para a outra coluna. O checkbox muda
  // sozinho (é um <label>), por isso aqui só se trata de o mover e de
  // acertar o sinal — "+" para juntar, "−" para tirar.
  form.addEventListener('change', function (e) {
    var campo = e.target;
    if (campo.name !== 'departments[]') { return; }

    var item = campo.closest('li');
    var destino = campo.checked ? colunas.tem : colunas.nao;

    campo.parentNode.querySelector('.dep-acao').textContent = campo.checked ? '−' : '+';
    destino.querySelector('[data-lista]').appendChild(item);
    item.style.display = '';

    atualizar(colunas.tem);
    atualizar(colunas.nao);
    if (procurar && procurar.value !== '') { filtrar(); }
  });

  function filtrar() {
    var q = procurar.value.trim().toLowerCase();
    // A pesquisa é da coluna "sem acesso", que é onde se vai buscar; quem já
    // tem acesso fica sempre à vista, que é o ponto deste ecrã.
    Array.prototype.forEach.call(colunas.nao.querySelectorAll('[data-lista] li'), function (li) {
      var bate = q === '' || (li.getAttribute('data-nome') || '').indexOf(q) !== -1;
      li.style.display = bate ? '' : 'none';
    });
    atualizar(colunas.nao);
  }

  if (procurar) { procurar.addEventListener('input', filtrar); }

  atualizar(colunas.tem);
  atualizar(colunas.nao);
})();
</script>
