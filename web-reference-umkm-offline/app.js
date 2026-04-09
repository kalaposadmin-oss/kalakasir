const networkBadge = document.getElementById("networkBadge");
const pendingCount = document.getElementById("pendingCount");
const lastTxStatus = document.getElementById("lastTxStatus");
const historyList = document.getElementById("historyList");
const sellButton = document.getElementById("sellButton");
const syncNowButton = document.getElementById("syncNowButton");

let pending = 0;
let txCounter = 0;

function setNetworkState(state) {
  networkBadge.className = "badge " + state;
  if (state === "online") networkBadge.textContent = "ONLINE";
  if (state === "offline") networkBadge.textContent = "OFFLINE";
  if (state === "syncing") networkBadge.textContent = "SYNCING";
  if (state === "error") networkBadge.textContent = "SYNC ERROR";
}

function addHistory(text) {
  const item = document.createElement("li");
  item.textContent = text;
  historyList.prepend(item);
}

sellButton.addEventListener("click", () => {
  txCounter += 1;
  pending += 1;
  pendingCount.textContent = String(pending);
  lastTxStatus.textContent = "PENDING";
  addHistory(`TRX-${txCounter} tersimpan lokal (PENDING)`);
});

syncNowButton.addEventListener("click", async () => {
  if (pending === 0) {
    addHistory("Tidak ada data yang perlu sinkronisasi.");
    return;
  }

  setNetworkState("syncing");
  await new Promise((r) => setTimeout(r, 900));
  setNetworkState("online");

  addHistory(`${pending} transaksi berhasil disinkronkan.`);
  pending = 0;
  pendingCount.textContent = "0";
  lastTxStatus.textContent = "SYNCED";
});

if (navigator.onLine) {
  setNetworkState("online");
} else {
  setNetworkState("offline");
}

window.addEventListener("online", () => setNetworkState("online"));
window.addEventListener("offline", () => setNetworkState("offline"));
