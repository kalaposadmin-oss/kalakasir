import featureConfig from '../../config/feature-gating.json';

const DEFAULT_PLAN = 'S';
const featuresByKey = new Map(
  (featureConfig.featureFlags || []).map((feature) => [feature.key, feature])
);

const normalizePlan = (plan) => {
  if (!plan) {
    return DEFAULT_PLAN;
  }
  return String(plan).trim().toUpperCase() || DEFAULT_PLAN;
};

const readMetaPlan = () => {
  const meta = document.querySelector('meta[name="tenant-plan"]');
  return meta?.content?.trim() || null;
};

export const getCurrentPlan = () => {
  return normalizePlan(window.currentPlan || readMetaPlan() || DEFAULT_PLAN);
};

export const canUse = (plan, key) => {
  const normalizedKey = String(key || '').trim();
  if (!normalizedKey) {
    return false;
  }
  const normalizedPlan = normalizePlan(plan);
  const feature = featuresByKey.get(normalizedKey);
  if (!feature || !Array.isArray(feature.plans)) {
    return false;
  }
  return feature.plans.includes(normalizedPlan);
};

export const applyFeatureGates = (plan = getCurrentPlan(), root = document) => {
  const normalizedPlan = normalizePlan(plan);
  root.querySelectorAll('[data-feature-key]').forEach((element) => {
    const key = element.getAttribute('data-feature-key');
    if (!key) {
      return;
    }
    element.classList.toggle('hidden', !canUse(normalizedPlan, key));
  });
};

window.FeatureGate = {
  canUse,
  applyFeatureGates,
  getCurrentPlan,
};
