<style>
    .sadmin-institute-main .institute-admin-page { padding-top: 1.25rem; }
    .sadmin-institute-main .institute-register-card { border: 0; border-radius: .65rem; box-shadow: 0 .85rem 1.85rem rgba(15, 23, 42, .045); overflow: hidden; }
    .sadmin-institute-main .institute-register-card .card-header { padding: .75rem 1rem; border-bottom: 1px solid var(--default-border); background: var(--custom-white); }
    .sadmin-institute-main .erp-tenant-table { width: 100%; min-width: 0; table-layout: fixed; }
    .sadmin-institute-main .erp-tenant-table th:nth-child(1) { width: 18%; }
    .sadmin-institute-main .erp-tenant-table th:nth-child(2) { width: 21%; }
    .sadmin-institute-main .erp-tenant-table th:nth-child(3) { width: 13%; }
    .sadmin-institute-main .erp-tenant-table th:nth-child(4) { width: 18%; }
    .sadmin-institute-main .erp-tenant-table th:nth-child(5) { width: 10%; }
    .sadmin-institute-main .erp-tenant-table th:last-child { width: 20%; }
    .sadmin-institute-main .gr-erp-tenant-scroll { overflow-x: hidden !important; overflow-y: visible; }
    .sadmin-institute-main .institute-request-table th,
    .sadmin-institute-main .institute-request-table td { padding: .42rem .65rem !important; font-size: .73rem; line-height: 1.2; vertical-align: middle; }
    .sadmin-institute-main .institute-request-table th { background: var(--default-background); font-size: .65rem; letter-spacing: .025em; }
    .sadmin-institute-main .gr-cell-title { display: block; font-size: .74rem; line-height: 1.2; white-space: normal; word-break: break-word; }
    .sadmin-institute-main .gr-cell-subtitle { display: block; margin-top: .15rem; color: #64748b; font-size: .67rem; line-height: 1.2; white-space: normal; word-break: break-word; }
    .sadmin-institute-main .tenant-admin-actions { display: grid; gap: .35rem; min-width: 0; }
    .sadmin-institute-main .tenant-renew-form { display: grid; grid-template-columns: 6rem 3.5rem 4.5rem minmax(6rem, 1fr) auto auto; gap: .25rem; align-items: center; margin: 0; }
    .sadmin-institute-main .tenant-renew-form .form-control,
    .sadmin-institute-main .tenant-renew-form .btn-sm { min-height: 1.7rem; padding: .18rem .45rem; font-size: .68rem; }
    .sadmin-institute-main .tenant-onboard-action { display: grid; gap: .25rem; margin: 0; }
    .sadmin-institute-main .tenant-onboard-action span { color: #64748b; font-size: .68rem; }
    .sadmin-institute-main .tenant-modal-action-cell { text-align: center; }
    .sadmin-institute-main .tenant-row-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .35rem; }
    .sadmin-institute-main .tenant-row-actions .btn { min-height: 1.85rem; padding: .22rem .45rem; font-size: .68rem; font-weight: 800; }
    .sadmin-institute-main .tenant-action-modal { display: none; position: fixed; inset: 0; z-index: 6000; align-items: center; justify-content: center; padding: 1rem; background: rgba(3, 21, 38, .48); text-align: left; }
    .sadmin-institute-main .tenant-action-modal.open { display: flex; }
    .sadmin-institute-main .tenant-action-panel { width: min(640px, 96vw); max-height: 88vh; display: flex; flex-direction: column; border: 1px solid #c9d9e8; border-top: 4px solid #f68a00; border-radius: 6px; background: #fff; box-shadow: 0 18px 45px rgba(0,0,0,.25); overflow: hidden; }
    .sadmin-institute-main .tenant-action-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 1rem; border-bottom: 1px solid #dbe7f2; background: #f6f9fc; }
    .sadmin-institute-main .tenant-action-head strong,
    .sadmin-institute-main .tenant-action-head span { display: block; }
    .sadmin-institute-main .tenant-action-head strong { color: #082f55; font-size: .95rem; line-height: 1.2; }
    .sadmin-institute-main .tenant-action-head span { margin-top: .2rem; color: #64748b; font-size: .72rem; }
    .sadmin-institute-main .tenant-modal-close { display: inline-flex; align-items: center; justify-content: center; width: 34px; min-height: 34px; border: 1px solid #c9d9e8; border-radius: 3px; background: #fff; color: #0a3c66; font-size: 1.25rem; }
    .sadmin-institute-main .tenant-action-body { display: grid; gap: .85rem; padding: 1rem; overflow: auto; }
    .sadmin-institute-main .tenant-summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .55rem; }
    .sadmin-institute-main .tenant-summary-grid div { padding: .55rem .65rem; border: 1px solid #d6e4f0; border-radius: 4px; background: #f8fbfd; }
    .sadmin-institute-main .tenant-summary-grid span,
    .sadmin-institute-main .tenant-renew-modal-form label span { display: block; color: #64748b; font-size: .64rem; font-weight: 800; text-transform: uppercase; }
    .sadmin-institute-main .tenant-summary-grid strong { display: block; margin-top: .2rem; color: #102a43; font-size: .75rem; line-height: 1.25; overflow-wrap: anywhere; }
    .sadmin-institute-main .tenant-action-strip { display: flex; flex-wrap: wrap; gap: .45rem; }
    .sadmin-institute-main .tenant-action-strip form { margin: 0; }
    .sadmin-institute-main .tenant-renew-modal-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; margin: 0; }
    .sadmin-institute-main .tenant-renew-modal-form label { display: grid; gap: .3rem; margin: 0; }
    .sadmin-institute-main .tenant-renew-modal-form .form-control { min-height: 2rem; font-size: .74rem; }
    .sadmin-institute-main .tenant-action-buttons { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .45rem; grid-column: 1 / -1; }
    .sadmin-institute-main .tenant-action-buttons .btn { min-height: 2rem; font-size: .74rem; font-weight: 800; }
    @media (max-width: 767.98px) {
        .sadmin-institute-main .tenant-row-actions,
        .sadmin-institute-main .tenant-summary-grid,
        .sadmin-institute-main .tenant-renew-modal-form { grid-template-columns: 1fr; }
    }
</style>
