<template>
  <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="mb-4 text-lg font-semibold text-slate-900">
        {{ editMode ? 'Modifier l’adhérent' : 'Nouvelle adhésion' }}
      </h2>

      <form @submit.prevent="saveMember" class="space-y-4" v-show="!submitted">
        <div>
          <label for="subdate" class="mb-1 block text-sm font-medium text-slate-700">Date d’adhésion</label>
          <input
            id="subdate"
            ref="subdate"
            type="date"
            v-model="form.subscription_date"
            @change="focusFilling"
            class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
          />
          <p v-if="errors.subscription_date" class="mt-1 text-sm text-red-600">{{ errors.subscription_date[0] }}</p>
        </div>

        <fieldset>
          <legend class="mb-1 block text-sm font-medium text-slate-700">Nature de l’adhérent</legend>
          <div class="flex gap-6">
            <label class="flex items-center gap-2 text-sm">
              <input type="radio" value="phy" v-model="form.morphy" name="morphy" class="text-indigo-600" />
              Physique
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input type="radio" value="mor" v-model="form.morphy" name="morphy" class="text-indigo-600" />
              Morale
            </label>
          </div>
        </fieldset>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label for="firstname" class="mb-1 block text-sm font-medium text-slate-700">Prénom</label>
            <input
              id="firstname"
              ref="firstname"
              type="text"
              v-model.trim="form.firstname"
              :disabled="form.morphy === 'mor'"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none disabled:bg-slate-100"
            />
            <p v-if="errors.firstname" class="mt-1 text-sm text-red-600">{{ errors.firstname[0] }}</p>
          </div>
          <div>
            <label for="lastname" class="mb-1 block text-sm font-medium text-slate-700">Nom</label>
            <input
              id="lastname"
              type="text"
              v-model.trim="form.lastname"
              :disabled="form.morphy === 'mor'"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none disabled:bg-slate-100"
            />
            <p v-if="errors.lastname" class="mt-1 text-sm text-red-600">{{ errors.lastname[0] }}</p>
          </div>
        </div>

        <div v-show="form.morphy === 'mor'">
          <label for="company" class="mb-1 block text-sm font-medium text-slate-700">Société</label>
          <input
            id="company"
            type="text"
            v-model.trim="form.company"
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
          />
          <p v-if="errors.company" class="mt-1 text-sm text-red-600">{{ errors.company[0] }}</p>
        </div>

        <div>
          <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
          <input
            id="email"
            type="email"
            v-model.trim="form.email"
            class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
          />
          <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email[0] }}</p>
        </div>

        <label v-if="editMode && subscriptionMode === 'floating'" class="flex w-fit items-center gap-2 text-sm text-slate-600">
          <input type="checkbox" v-model="renewal" class="rounded border-slate-300 text-indigo-600" />
          Reporter la fin d’adhésion depuis la période en cours (réadhésion)
        </label>

        <div v-if="form.subscription_date" class="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-600">
          Fin d’adhésion calculée :
          <strong class="text-slate-900">{{ computedEnd }}</strong>
        </div>

        <div class="flex gap-3">
          <button
            type="submit"
            :disabled="!isValid || saving"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-40"
          >
            {{ editMode ? 'Enregistrer' : 'Appliquer' }}
          </button>
          <button
            v-if="editMode"
            type="button"
            @click="stopEdit"
            class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50"
          >
            Annuler
          </button>
        </div>
      </form>

      <div v-if="submitted" class="space-y-4">
        <p class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Saisie réussie !</p>
        <button
          type="button"
          ref="newBtn"
          @click="resetForm"
          class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
        >
          Nouveau
        </button>
      </div>
    </section>

    <aside class="space-y-6">
      <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold text-slate-900">Recherche ({{ members.length }})</h3>
        <p v-if="members.length === 0" class="text-sm text-slate-500">Aucun adhérent trouvé.</p>
        <ul class="divide-y divide-slate-100">
          <li
            v-for="member in members"
            :key="member.id"
            @click="selectMember(member)"
            class="cursor-pointer px-2 py-2 text-sm transition"
            :class="current && current.id === member.id ? 'rounded-md bg-indigo-50 font-medium text-indigo-700' : 'hover:bg-slate-50'"
          >
            {{ memberDisplay(member) }}
            <span class="block text-xs text-slate-400">{{ member.morphy === 'mor' ? 'Personne morale' : 'Personne physique' }}</span>
          </li>
        </ul>
      </section>

      <section v-if="current" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold text-slate-900">Adhérent</h3>
        <dl class="space-y-1.5 text-sm">
          <div v-if="current.morphy === 'mor'" class="flex justify-between gap-2">
            <dt class="text-slate-500">Société</dt>
            <dd class="text-right">{{ current.company }}</dd>
          </div>
          <div class="flex justify-between gap-2">
            <dt class="text-slate-500">Prénom</dt>
            <dd class="text-right">{{ current.firstname || '—' }}</dd>
          </div>
          <div class="flex justify-between gap-2">
            <dt class="text-slate-500">Nom</dt>
            <dd class="text-right">{{ current.lastname || '—' }}</dd>
          </div>
          <div class="flex justify-between gap-2">
            <dt class="text-slate-500">Email</dt>
            <dd class="text-right">{{ current.email || '—' }}</dd>
          </div>
          <div class="flex justify-between gap-2">
            <dt class="text-slate-500">Fin d’adhésion</dt>
            <dd class="text-right">{{ current.subscription_end ? formatShort(current.subscription_end) : 'Néant' }}</dd>
          </div>
        </dl>
        <div class="mt-4 flex gap-2">
          <button
            v-if="!editMode"
            type="button"
            @click="editMember"
            class="rounded-md border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 transition hover:bg-amber-100"
          >
            Éditer
          </button>
          <button
            type="button"
            @click="deleteMember"
            class="rounded-md border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100"
          >
            Supprimer
          </button>
        </div>
      </section>

      <p v-else class="text-sm text-slate-500">Sélectionner un adhérent pour l’afficher.</p>
    </aside>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const subscriptionMode = document.getElementById('app')?.dataset.subscriptionMode || 'floating';

const today = new Date().toISOString().slice(0, 10);

const defaultForm = () => ({
  id: null,
  morphy: 'phy',
  firstname: '',
  lastname: '',
  company: '',
  email: '',
  subscription_date: '',
});

const form = reactive(defaultForm());
const renewal = ref(false);
const submitted = ref(false);
const editMode = ref(false);
const saving = ref(false);
const errors = ref({});
const members = ref([]);
const current = ref(null);

const subdate = ref(null);
const firstname = ref(null);
const newBtn = ref(null);

const isValid = computed(() => {
  const identityOk =
    form.morphy === 'phy'
      ? form.firstname.length > 0 && form.lastname.length > 0
      : form.company.length > 0;
  const emailOk = form.email === '' || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(form.email);
  return identityOk && emailOk && form.subscription_date !== '';
});

function subscriptionEndDate(value) {
  const date = new Date(value);
  if (subscriptionMode === 'fixed') {
    const end = new Date(date.getFullYear(), 6, 31);
    if (date >= end) end.setFullYear(end.getFullYear() + 1);
    return end;
  }
  date.setFullYear(date.getFullYear() + 1);
  date.setDate(date.getDate() - 1);
  return date;
}

const computedEnd = computed(() => {
  if (form.subscription_date === '') return '';
  return formatShort(subscriptionEndDate(form.subscription_date).toISOString().slice(0, 10));
});

function memberDisplay(member) {
  if (member.morphy === 'mor') return member.company || 'Personne morale';
  return [member.firstname, member.lastname].filter(Boolean).join(' ') || 'Sans nom';
}

function formatShort(value) {
  const date = value instanceof Date ? value : new Date(value);
  return new Intl.DateTimeFormat('fr-FR').format(date);
}

async function fetchJson(url, options = {}) {
  const response = await fetch(url, {
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfToken,
      Accept: 'application/json',
    },
    ...options,
  });

  if (response.status === 422) {
    const body = await response.json();
    throw Object.assign(new Error('validation'), { validationErrors: body.errors ?? {} });
  }

  if (!response.ok) {
    throw new Error(`Erreur HTTP ${response.status}`);
  }

  return response.status === 204 ? null : response.json();
}

async function refreshList() {
  if (editMode.value) return;

  const params = new URLSearchParams();
  for (const field of ['firstname', 'lastname', 'company', 'email']) {
    if (form[field].trim() !== '') params.set(field, form[field].trim());
  }

  const query = params.toString();
  members.value = await fetchJson(`/api/members${query ? `?${query}` : ''}`);
  current.value = null;
}

let searchTimer;
watch(
  () => [form.firstname, form.lastname, form.company, form.email],
  () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      refreshList().catch(() => {
        members.value = [];
      });
    }, 250);
  },
);

async function saveMember() {
  if (!isValid.value || saving.value) return;

  saving.value = true;
  errors.value = {};

  const payload = { ...form, renewal: renewal.value };
  const method = editMode.value ? 'PUT' : 'POST';
  const url = editMode.value ? `/api/members/${form.id}` : '/api/members';

  try {
    await fetchJson(url, { method, body: JSON.stringify(payload) });
    submitted.value = true;
    editMode.value = false;
    current.value = null;
    await nextTick();
    newBtn.value?.focus();
  } catch (error) {
    if (error.validationErrors) {
      errors.value = error.validationErrors;
    } else {
      alert(error.message);
    }
  } finally {
    saving.value = false;
  }
}

async function selectMember(member) {
  if (editMode.value) return;
  current.value = await fetchJson(`/api/members/${member.id}`);
}

function editMember() {
  Object.assign(form, {
    id: current.value.id,
    morphy: current.value.morphy,
    firstname: current.value.firstname ?? '',
    lastname: current.value.lastname ?? '',
    company: current.value.company ?? '',
    email: current.value.email ?? '',
    subscription_date: current.value.subscription_date ?? '',
  });
  renewal.value = false;
  submitted.value = false;
  editMode.value = true;
  focusFilling();
}

function stopEdit() {
  editMode.value = false;
  resetForm();
}

async function deleteMember() {
  if (!current.value || !window.confirm('Supprimer définitivement cet adhérent ?')) return;

  await fetchJson(`/api/members/${current.value.id}`, { method: 'DELETE' });
  current.value = null;
  resetForm();
}

async function resetForm() {
  Object.assign(form, defaultForm());
  renewal.value = false;
  submitted.value = false;
  editMode.value = false;
  errors.value = {};
  await refreshList().catch(() => {});
  focusFilling();
}

function focusFilling() {
  if (form.subscription_date !== '') {
    firstname.value?.focus();
  } else {
    subdate.value?.focus();
  }
}

onMounted(() => {
  resetForm().then(() => {
    form.subscription_date = today;
    focusFilling();
  });
});
</script>
