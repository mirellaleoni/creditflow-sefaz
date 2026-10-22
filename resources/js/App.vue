<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans selection:bg-emerald-500 selection:text-white">
    <!-- Barra Superior -->
    <header class="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-md sticky top-0 z-40">
      <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/20 font-extrabold text-slate-950 text-xl tracking-tighter">
            CF
          </div>
          <div>
            <span class="text-lg font-bold tracking-tight text-white flex items-center gap-2">
              Credit<span class="text-emerald-400">Flow</span>
              <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">SEFAZ</span>
            </span>
          </div>
        </div>

        <!-- Se estiver logado -->
        <div v-if="token" class="flex items-center gap-3">
          <div class="text-right hidden sm:block">
            <p class="text-xs text-slate-400 font-medium leading-none">Conectado como</p>
            <p class="text-sm font-semibold text-slate-200">{{ currentUser?.name || 'Usuário' }}</p>
          </div>
          <button 
            @click="logout"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-300 hover:text-rose-400 bg-slate-800/80 hover:bg-slate-800 border border-slate-700/60 transition flex items-center gap-1.5 cursor-pointer"
          >
            Sair
          </button>
        </div>
      </div>
    </header>

    <!-- Conteúdo Principal -->
    <main class="flex-1 max-w-6xl w-full mx-auto px-4 py-8">
      <!-- 1. TELA DE AUTENTICAÇÃO (Se não estiver logado) -->
      <div v-if="!token" class="max-w-md mx-auto py-8">
        <div class="bg-slate-900/80 border border-slate-800/80 rounded-2xl p-6 sm:p-8 shadow-2xl backdrop-blur-xl">
          <!-- Alternador Login / Cadastro -->
          <div class="flex p-1 bg-slate-950 rounded-xl border border-slate-800 mb-6">
            <button
              @click="authMode = 'login'"
              :class="authMode === 'login' ? 'bg-slate-800 text-white shadow' : 'text-slate-400 hover:text-slate-200'"
              class="flex-1 py-2 text-xs font-bold rounded-lg transition"
            >
              Entrar
            </button>
            <button
              @click="authMode = 'register'"
              :class="authMode === 'register' ? 'bg-slate-800 text-white shadow' : 'text-slate-400 hover:text-slate-200'"
              class="flex-1 py-2 text-xs font-bold rounded-lg transition"
            >
              Criar Conta
            </button>
          </div>

          <h2 class="text-xl font-bold text-white mb-1">
            {{ authMode === 'login' ? 'Acesse sua conta' : 'Crie sua conta no CreditFlow' }}
          </h2>
          <p class="text-xs text-slate-400 mb-6">
            {{ authMode === 'login' ? 'Informe suas credenciais para gerenciar seus créditos' : 'Cada nova conta já nasce com saldo inicial de R$ 0,00' }}
          </p>

          <!-- Formulário -->
          <form @submit.prevent="authMode === 'login' ? handleLogin() : handleRegister()" class="space-y-4">
            <div v-if="authMode === 'register'">
              <label class="block text-xs font-semibold text-slate-300 mb-1">Nome Completo</label>
              <input
                v-model="authForm.name"
                type="text"
                required
                placeholder="Ex: Maria Silva"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">E-mail</label>
              <input
                v-model="authForm.email"
                type="email"
                required
                placeholder="seu.email@exemplo.com"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">Senha</label>
              <input
                v-model="authForm.password"
                type="password"
                required
                :placeholder="authMode === 'register' ? 'Mínimo 8 caracteres (A-z, 0-9)' : 'Sua senha'"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition"
              />
            </div>

            <div v-if="authMode === 'register'">
              <label class="block text-xs font-semibold text-slate-300 mb-1">Confirme a Senha</label>
              <input
                v-model="authForm.password_confirmation"
                type="password"
                required
                placeholder="Repita a senha"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition"
              />
            </div>

            <button
              type="submit"
              :disabled="loading"
              class="w-full py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-bold text-sm shadow-lg shadow-emerald-500/20 transition disabled:opacity-50 cursor-pointer"
            >
              <span v-if="loading">Processando...</span>
              <span v-else>{{ authMode === 'login' ? 'Entrar' : 'Cadastrar e Abrir Conta' }}</span>
            </button>
          </form>

          <!-- Atalho Usuário Demo -->
          <div class="mt-6 pt-5 border-t border-slate-800/80 text-center">
            <p class="text-xs text-slate-400 mb-2">Para testar rapidamente com dados do seeder:</p>
            <button
              type="button"
              @click="prefillDemo"
              class="text-xs font-semibold text-emerald-400 hover:text-emerald-300 transition underline underline-offset-4 cursor-pointer"
            >
              Preencher com Usuário Demo (demo@creditflow.local)
            </button>
          </div>
        </div>
      </div>

      <!-- 2. DASHBOARD FINANCEIRO (Logado) -->
      <div v-else class="space-y-8">
        <!-- Hero: Card de Saldo e Ações Rápidas -->
        <div class="bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 border border-slate-800 rounded-3xl p-6 sm:p-8 relative overflow-hidden shadow-2xl">
          <!-- Efeito de luz decorativo -->
          <div class="absolute -right-20 -top-20 w-80 h-80 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none"></div>

          <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
              <div class="flex items-center gap-2 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Saldo Disponível em Conta</span>
                <button 
                  @click="fetchAccount" 
                  title="Atualizar saldo"
                  class="text-slate-400 hover:text-white transition cursor-pointer"
                >
                  <svg class="w-4 h-4" :class="{ 'animate-spin': loadingAccount }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </button>
              </div>
              <div class="flex items-baseline gap-2">
                <span class="text-4xl sm:text-5xl font-black text-white tracking-tight">
                  R$ {{ account?.balance || '0.00' }}
                </span>
                <span class="text-xs font-medium text-slate-400">
                  ({{ (account?.balance_cents || 0).toLocaleString('pt-BR') }} centavos)
                </span>
              </div>
            </div>

            <!-- Botões de Ação -->
            <div class="flex flex-wrap items-center gap-3">
              <button
                @click="openTransactionModal('credit')"
                class="px-5 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-bold text-sm flex items-center gap-2 shadow-lg shadow-emerald-500/20 transition cursor-pointer"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Adicionar Crédito
              </button>

              <button
                @click="openTransactionModal('debit')"
                class="px-5 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-rose-400 hover:text-rose-300 border border-slate-700/60 font-bold text-sm flex items-center gap-2 transition cursor-pointer"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>
                Registrar Débito
              </button>
            </div>
          </div>
        </div>

        <!-- Seção do Extrato -->
        <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-6 shadow-xl">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
              <h3 class="text-lg font-bold text-white">Extrato de Movimentações</h3>
              <p class="text-xs text-slate-400">Histórico completo e auditável das suas operações</p>
            </div>

            <!-- Filtro de Tipo -->
            <div class="flex items-center gap-1.5 p-1 bg-slate-950 rounded-xl border border-slate-800 text-xs">
              <button
                v-for="filter in [
                  { label: 'Todos', value: '' },
                  { label: 'Crédito', value: 'credit' },
                  { label: 'Débito', value: 'debit' },
                  { label: 'Estorno', value: 'reversal' }
                ]"
                :key="filter.value"
                @click="filterType = filter.value; fetchTransactions(1)"
                :class="filterType === filter.value ? 'bg-slate-800 text-white font-bold' : 'text-slate-400 hover:text-slate-200'"
                class="px-3 py-1.5 rounded-lg transition cursor-pointer"
              >
                {{ filter.label }}
              </button>
            </div>
          </div>

          <!-- Tabela -->
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead>
                <tr class="border-b border-slate-800 text-slate-400 text-xs uppercase tracking-wider">
                  <th class="py-3 px-4">ID</th>
                  <th class="py-3 px-4">Data / Hora</th>
                  <th class="py-3 px-4">Tipo</th>
                  <th class="py-3 px-4">Valor</th>
                  <th class="py-3 px-4">Saldo Após</th>
                  <th class="py-3 px-4">Idempotência</th>
                  <th class="py-3 px-4 text-right">Ação</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60">
                <tr v-if="loadingTransactions" class="text-slate-400 text-center">
                  <td colspan="7" class="py-8">Carregando movimentações...</td>
                </tr>
                <tr v-else-if="transactions.length === 0" class="text-slate-400 text-center">
                  <td colspan="7" class="py-8">Nenhuma movimentação encontrada neste período/filtro.</td>
                </tr>
                <tr 
                  v-else 
                  v-for="tx in transactions" 
                  :key="tx.id"
                  class="hover:bg-slate-800/30 transition text-slate-200"
                >
                  <td class="py-3.5 px-4 font-mono text-xs text-slate-400">#{{ tx.id }}</td>
                  <td class="py-3.5 px-4 text-xs">{{ formatDate(tx.created_at) }}</td>
                  <td class="py-3.5 px-4">
                    <span 
                      v-if="tx.type === 'credit'" 
                      class="px-2 py-0.5 rounded-md text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"
                    >
                      Crédito
                    </span>
                    <span 
                      v-else-if="tx.type === 'debit'" 
                      class="px-2 py-0.5 rounded-md text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20"
                    >
                      Débito
                    </span>
                    <span 
                      v-else 
                      class="px-2 py-0.5 rounded-md text-xs font-semibold bg-purple-500/10 text-purple-400 border border-purple-500/20"
                    >
                      Estorno (ref #{{ tx.reverses_transaction_id }})
                    </span>
                  </td>
                  <td class="py-3.5 px-4 font-semibold" :class="tx.type === 'credit' ? 'text-emerald-400' : (tx.type === 'debit' ? 'text-rose-400' : 'text-purple-400')">
                    {{ tx.type === 'debit' ? '-' : '+' }} R$ {{ tx.amount }}
                  </td>
                  <td class="py-3.5 px-4 text-xs font-medium text-slate-300">R$ {{ tx.balance_after }}</td>
                  <td class="py-3.5 px-4 font-mono text-[11px] text-slate-400 max-w-[140px] truncate" :title="tx.idempotency_key">
                    {{ tx.idempotency_key || '—' }}
                  </td>
                  <td class="py-3.5 px-4 text-right">
                    <button
                      v-if="tx.type !== 'reversal'"
                      @click="handleReverse(tx.id)"
                      :disabled="loadingAction === tx.id"
                      class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-800 hover:bg-rose-500/20 text-slate-300 hover:text-rose-300 border border-slate-700 hover:border-rose-500/40 transition disabled:opacity-40 cursor-pointer"
                    >
                      {{ loadingAction === tx.id ? 'Estornando...' : 'Estornar' }}
                    </button>
                    <span v-else class="text-xs text-slate-600 font-medium italic">Compensatória</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Paginação -->
          <div v-if="pagination.last_page > 1" class="flex items-center justify-between border-t border-slate-800/80 pt-4 mt-4">
            <span class="text-xs text-slate-400">
              Página {{ pagination.current_page }} de {{ pagination.last_page }} ({{ pagination.total }} operações)
            </span>
            <div class="flex items-center gap-2">
              <button
                :disabled="pagination.current_page <= 1"
                @click="fetchTransactions(pagination.current_page - 1)"
                class="px-3 py-1 text-xs font-semibold rounded-lg bg-slate-800 border border-slate-700 text-slate-300 disabled:opacity-40 transition cursor-pointer"
              >
                Anterior
              </button>
              <button
                :disabled="pagination.current_page >= pagination.last_page"
                @click="fetchTransactions(pagination.current_page + 1)"
                class="px-3 py-1 text-xs font-semibold rounded-lg bg-slate-800 border border-slate-700 text-slate-300 disabled:opacity-40 transition cursor-pointer"
              >
                Próxima
              </button>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- MODAL DE TRANSAÇÃO (CRÉDITO OU DÉBITO) -->
    <div v-if="modal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl p-6 shadow-2xl relative">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-bold text-white">
            {{ modal.type === 'credit' ? 'Registrar Crédito (Depósito)' : 'Registrar Débito (Saque)' }}
          </h3>
          <button @click="modal.show = false" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
        </div>

        <form @submit.prevent="submitTransaction" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Valor em Reais (R$)</label>
            <div class="relative">
              <span class="absolute left-3.5 top-2.5 text-slate-400 text-sm font-semibold">R$</span>
              <input
                v-model="modal.amount"
                type="number"
                step="0.01"
                min="0.01"
                required
                placeholder="50,00"
                class="w-full pl-11 pr-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-sm focus:outline-none focus:border-emerald-500 font-bold transition"
              />
            </div>
            <p class="text-[11px] text-slate-400 mt-1">
              Será enviado como {{ Math.round((Number(modal.amount) || 0) * 100) }} centavos inteiros.
            </p>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="text-xs font-semibold text-slate-300">Chave de Idempotência</label>
              <button
                type="button"
                @click="generateNewKey"
                class="text-[11px] text-emerald-400 hover:text-emerald-300 font-semibold cursor-pointer"
              >
                Gerar Nova
              </button>
            </div>
            <input
              v-model="modal.idempotencyKey"
              type="text"
              required
              class="w-full px-3.5 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-300 text-xs font-mono focus:outline-none focus:border-emerald-500 transition"
            />
            <p class="text-[11px] text-slate-500 mt-1">
              Repetir a mesma chave testa a idempotência sem cobrança duplicada.
            </p>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
            <button
              type="button"
              @click="modal.show = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white transition cursor-pointer"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="loading"
              class="px-5 py-2 rounded-xl font-bold text-xs text-slate-950 transition disabled:opacity-50 cursor-pointer"
              :class="modal.type === 'credit' ? 'bg-emerald-400 hover:bg-emerald-300' : 'bg-rose-400 hover:bg-rose-300'"
            >
              <span v-if="loading">Enviando...</span>
              <span v-else>Confirmar {{ modal.type === 'credit' ? 'Crédito' : 'Débito' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- TOAST DE NOTIFICAÇÃO -->
    <div
      v-if="toast.show"
      class="fixed bottom-6 right-6 z-50 px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3 text-xs font-semibold border backdrop-blur-md transition-all animate-bounce"
      :class="toast.type === 'error' ? 'bg-rose-950/90 text-rose-200 border-rose-800' : 'bg-emerald-950/90 text-emerald-200 border-emerald-800'"
    >
      <span>{{ toast.message }}</span>
      <button @click="toast.show = false" class="text-slate-400 hover:text-white ml-2 cursor-pointer">✕</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const token = ref(localStorage.getItem('creditflow_token') || '');
const currentUser = ref(JSON.parse(localStorage.getItem('creditflow_user') || 'null'));

const authMode = ref('login');
const loading = ref(false);
const loadingAccount = ref(false);
const loadingTransactions = ref(false);
const loadingAction = ref(null);

const authForm = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
});

const account = ref(null);
const transactions = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
const filterType = ref('');

const modal = ref({
  show: false,
  type: 'credit',
  amount: '',
  idempotencyKey: ''
});

const toast = ref({
  show: false,
  message: '',
  type: 'success'
});

function showToast(message, type = 'success') {
  toast.value = { show: true, message, type };
  setTimeout(() => {
    toast.value.show = false;
  }, 4500);
}

function generateUuid() {
  return 'cf-' + Math.random().toString(36).substring(2, 9) + '-' + Date.now();
}

function prefillDemo() {
  authMode.value = 'login';
  authForm.value.email = 'demo@creditflow.local';
  authForm.value.password = 'Demo1234!';
}

async function api(path, options = {}) {
  const headers = {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    ...(token.value ? { 'Authorization': `Bearer ${token.value}` } : {}),
    ...(options.headers || {})
  };

  const response = await fetch(path, {
    ...options,
    headers
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    const errorMsg = data.message || Object.values(data.errors || {})[0]?.[0] || 'Ocorreu um erro na requisição.';
    throw new Error(errorMsg);
  }

  return data;
}

async function handleLogin() {
  loading.value = true;
  try {
    const data = await api('/api/login', {
      method: 'POST',
      body: JSON.stringify({
        email: authForm.value.email,
        password: authForm.value.password
      })
    });

    token.value = data.token;
    currentUser.value = data.user;
    localStorage.setItem('creditflow_token', data.token);
    localStorage.setItem('creditflow_user', JSON.stringify(data.user));

    showToast('Login realizado com sucesso!');
    await loadDashboard();
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    loading.value = false;
  }
}

async function handleRegister() {
  loading.value = true;
  try {
    const data = await api('/api/register', {
      method: 'POST',
      body: JSON.stringify(authForm.value)
    });

    token.value = data.token;
    currentUser.value = data.user;
    localStorage.setItem('creditflow_token', data.token);
    localStorage.setItem('creditflow_user', JSON.stringify(data.user));

    showToast('Conta criada com sucesso! Saldo inicial R$ 0,00.');
    await loadDashboard();
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    loading.value = false;
  }
}

async function logout() {
  try {
    await api('/api/logout', { method: 'POST' });
  } catch (e) {
    // continua logout local
  }
  token.value = '';
  currentUser.value = null;
  localStorage.removeItem('creditflow_token');
  localStorage.removeItem('creditflow_user');
  showToast('Sessão encerrada.');
}

async function fetchAccount() {
  loadingAccount.value = true;
  try {
    const res = await api('/api/account');
    account.value = res.data;
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    loadingAccount.value = false;
  }
}

async function fetchTransactions(page = 1) {
  loadingTransactions.value = true;
  try {
    let url = `/api/transactions?page=${page}&per_page=10`;
    if (filterType.value) {
      url += `&type=${filterType.value}`;
    }
    const res = await api(url);
    transactions.value = res.data;
    pagination.value = {
      current_page: res.meta?.current_page || 1,
      last_page: res.meta?.last_page || 1,
      total: res.meta?.total || 0
    };
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    loadingTransactions.value = false;
  }
}

function openTransactionModal(type) {
  modal.value = {
    show: true,
    type,
    amount: '',
    idempotencyKey: generateUuid()
  };
}

function generateNewKey() {
  modal.value.idempotencyKey = generateUuid();
}

async function submitTransaction() {
  loading.value = true;
  const amountCents = Math.round(Number(modal.value.amount) * 100);

  if (amountCents <= 0) {
    showToast('Informe um valor positivo maior que zero.', 'error');
    loading.value = false;
    return;
  }

  const endpoint = modal.value.type === 'credit' 
    ? '/api/transactions/credit' 
    : '/api/transactions/debit';

  try {
    const res = await api(endpoint, {
      method: 'POST',
      headers: {
        'Idempotency-Key': modal.value.idempotencyKey
      },
      body: JSON.stringify({ amount_cents: amountCents })
    });

    showToast(`${modal.value.type === 'credit' ? 'Crédito' : 'Débito'} de R$ ${res.data.amount} realizado com sucesso!`);
    modal.value.show = false;
    await fetchAccount();
    await fetchTransactions(1);
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    loading.value = false;
  }
}

async function handleReverse(transactionId) {
  if (!confirm(`Deseja realmente estornar a movimentação #${transactionId}? Esta ação criará um lançamento compensatório.`)) {
    return;
  }

  loadingAction.value = transactionId;
  try {
    const res = await api(`/api/transactions/${transactionId}/reverse`, {
      method: 'POST'
    });

    showToast(`Estorno #${res.data.id} criado com sucesso! Saldo atualizado.`);
    await fetchAccount();
    await fetchTransactions(pagination.value.current_page);
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    loadingAction.value = null;
  }
}

function formatDate(isoStr) {
  if (!isoStr) return '—';
  const d = new Date(isoStr);
  return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

async function loadDashboard() {
  await Promise.all([
    fetchAccount(),
    fetchTransactions(1)
  ]);
}

onMounted(() => {
  if (token.value) {
    loadDashboard();
  }
});
</script>
