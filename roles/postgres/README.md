# Stand up the PostgreSQL training environment — Start Here

**You know AWS/Ansible basics but nothing about *this* class's setup. Read this one page
first.** It gives you the provisioning sequence, how long each step takes, and what to
verify — then fans out to the detail.

> This builds the environment for two courses, both with content in the
> **`training-material`** repo:
>
> | Class | Course | PostgreSQL | Content |
> | ----- | ------ | ---------- | ------- |
> | `pg-ops` | Percona PostgreSQL DBA & Operations | 17 (role default) | `pg-training/` |
> | `pg-dev` | PostgreSQL For Developers | **18**: add `-e pg_major=18` to step 3 | `pg-dev-deck/` (deck + `labs/`) |
>
> The SSH user on the training AMI (Rocky Linux 9) is `rocky`.

---

## What you're building

Per **student**: three EC2 nodes — `pg1` (primary + sysbench client), `pg2` (standby),
`pg3` (pgBackRest repo / DR / 3rd Patroni node / HAProxy+PgBouncer). Per **class**: one
shared `pmm` server. All run Percona Distribution for PostgreSQL (17 for `pg-ops`, 18 for
`pg-dev`) on the Rocky Linux 9 training AMI. The `pg` alias expands to one full `pg1,pg2,pg3` student set.

This `postgres` role brings each node up to a clean, **primary/standby-ready PostgreSQL
cluster** — Patroni/etcd/HAProxy/PgBouncer are installed *during the Block 3 labs*, not
pre-baked (standing them up is the point of those labs).

---

## The provisioning sequence

Everything runs from the top of this repo with `./bin/learnomancer` (see the main
[`README.md`](../../README.md) for install and AWS login). The PostgreSQL class is
**`pg-ops`**. `pg-dev` builds the same environment on PostgreSQL 18; the developer labs
use the three nodes as independent servers and install pgAdmin themselves. Do this **the day before class**, not
the morning of.

Example: class slug `ACME`, 8 students, region `us-west-2`:

| # | Step | Command | ~Time |
| - | ---- | ------- | ----- |
| 1 | Find the current training AMI | `./bin/learnomancer list-amis us-west-2` | — |
| 2 | **Create the class**: VPC + one pg set per team + shared PMM + inventory | `./bin/learnomancer create-class pg-ops ACME us-west-2 8 <ami>` | ~3 min |
| 3 | **Provision** (PMM first, then every pg node) | `ansible-playbook -i ansible_ACME hosts.yml` (`pg-dev`: add `-e pg_major=18`) | ~15 min |
| 4 | Print the connection sheet / dashboard URL | `./bin/learnomancer summary ACME` | — |
| 5 | Smoke-test (below) | — | ~5 min |
| — | **Tear down after class** | `./bin/learnomancer drop-class ACME us-west-2` | ~3 min |

If Ansible reports hosts as `UNREACHABLE` right after step 2, sshd isn't up yet. Wait a
minute and re-run step 3 (the role is safe to re-run).

> **What gets wired up for you (no manual steps):** `create-class` adds the security-group
> self-reference rule the HA labs need (5432, Patroni 8008, etcd 2379/2380, PgBouncer 6432,
> HAProxy 5000/5001 between nodes, nothing exposed to the internet). `hosts.yml` runs the
> `pmm` play first and waits for PMM to be ready, and the pg play **reads `pmm_server_ip`
> from the inventory**, so the nodes register with the shared PMM server without you
> copying an IP by hand.

<details>
<summary>Under the hood (what <code>create-class pg-ops</code> runs, if you ever need to drive it by hand)</summary>

```bash
./bin/learnomancer create-vpc       ACME us-west-2
./bin/learnomancer create-instances ACME us-west-2 8 pg  -i <ami>   # pg1,pg2,pg3 per team
./bin/learnomancer create-instances ACME us-west-2 1 pmm -i <ami>   # shared PMM (team T0)
./bin/learnomancer ansible-config   ACME us-west-2 --output         # writes ansible_ACME
./bin/learnomancer ssh-config       ACME us-west-2 --output
ansible-playbook -i ansible_ACME hosts.yml
```

To add students to a running class, use `create-instances … pg -o <current team count>`,
re-run `ansible-config`, then `ansible-playbook … --limit pg1:pg2:pg3`.
</details>

---

## Verify before you walk away (smoke test)

On any student's `pg1` (as `postgres`):

```sql
SELECT count(*) FROM bluebox.film;        -- expect 7836 — the dataset loaded
SHOW listen_addresses;                     -- '*' — remote connections allowed
SELECT 1 FROM pg_roles WHERE rolname='replicator';   -- exists — replication labs ready
```

And from the shell: `systemctl is-active postgresql-17` (or `postgresql-18` for `pg-dev`) → `active`; the `pmm` server's web
UI is reachable on `https://<pmm-public-ip>` (port 443). If `bluebox.film` is empty or the
`postgres` password isn't set, re-run the `pg` play — those are the two things that bite.

---

## Fan out to the detail

- **`pg-training/infra/README.md`** (course repo) — topology rationale and the two-pass
  SSH-key note for pgBackRest. This role and `hosts.yml` here are the source of truth for
  provisioning.
- **[`tasks/main.yml`](tasks/main.yml)** — the exact install/config sequence on every pg node.
- **[`tasks/pg1.yml`](tasks/pg1.yml)** — pg1-only extras (Bluebox dataset, `replicator`/`sysbench` roles, PostGIS).
- **[`tasks/pmm.yml`](tasks/pmm.yml)** — the shared PMM server setup.
- **[`defaults/main.yml`](defaults/main.yml)** — passwords and tunables (keep in sync with the labs).
- **Per-AMI gotchas** (package names, etcd, sysbench path) — `INSTRUCTOR-NOTES.md` in the course repo.
- **Proof it works** — `LAB-VALIDATION-REPORT.md` in the course repo records a full end-to-end run.

> The top-level [`README.md`](../../README.md) "machine types" section covers the `pg`/`pmm`
> aliases and the security-group rule in the context of all the training machine types.
