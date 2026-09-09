@once
@push('styles')
<style>
    .sz-detail { max-width: 1240px; margin: 0 auto; font-size: 14px; }
    .sz-detail .detail-head h1 { font-size: 24px; margin: 8px 0 0; overflow-wrap: anywhere; }
    .sz-detail .cardx, .sz-detail .staff-card, .sz-ticket .card { border: 1px solid #e1e7e5; border-radius: 12px; box-shadow: none; }
    .sz-detail .cardx h2 { font-size: 16px; font-weight: 650; margin-bottom: 16px; }
    .sz-detail .info { grid-template-columns: 120px minmax(0, 1fr); margin: 0; }
    .sz-detail .info dt, .sz-detail .info dd { font-size: 14px; padding: 12px 0; margin: 0; overflow-wrap: anywhere; }
    .sz-detail .info dt { color: #687b83; font-weight: 400; }
    .sz-detail .formx label { font-size: 12px; font-weight: 600; color: #50636c; }
    .sz-detail .audit { font-size: 13px; padding: 16px 0; }
    .sz-detail .audit small { color: #687b83; margin: 4px 0; }
    .sz-audit-heading { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 6px 16px; }
    .sz-audit-heading strong { font-weight: 600; font-size: 14px; }
    .sz-audit-heading time { color: #72828a; font-size: 12px; }
    .sz-audit-actor { color: #72828a; font-size: 12px; margin: 5px 0 12px; }
    .sz-audit-changes { margin: 0; padding: 12px 14px; background: #f6f8f7; border-radius: 8px; }
    .sz-audit-changes:empty { display: none; }
    .sz-audit-changes > div { display: flex; flex-wrap: wrap; gap: 4px 16px; padding: 4px 0; }
    .sz-audit-changes dt { width: 110px; font-weight: 400; color: #687b83; }
    .sz-audit-changes dd { flex: 1; min-width: 180px; margin: 0; overflow-wrap: anywhere; font-weight: 500; }
    .sz-audit-before { color: #72828a; font-weight: 400; }
    .sz-audit-arrow { margin: 0 8px; color: #72828a; }
    .sz-audit-reason { margin: 10px 0 0; overflow-wrap: anywhere; color: #50636c; }
    .sz-audit-reason > span { color: #72828a; }
    .sz-audit-entry:last-child { border-bottom: 0; padding-bottom: 0; }
    .sz-detail .audit pre { white-space: pre-wrap; overflow-wrap: anywhere; font-size: 12px; background: #f5f7f6; padding: 12px; border-radius: 8px; }
    .sz-disclosure > summary { cursor: pointer; color: #15734f; font-size: 14px; font-weight: 600; padding: 10px 0; }
    .sz-disclosure > summary:focus-visible, .sz-event-note > summary:focus-visible { outline: 2px solid #13875b; outline-offset: 4px; border-radius: 3px; }
    .sz-refunds > p { font-size: 13px; color: #687b83; line-height: 1.6; margin-bottom: 10px; }
    .sz-refunds article { font-size: 13px; }
    .sz-refunds article > p { margin: 6px 0; color: #50636c; }
    .sz-timeline { background: #fff; border: 1px solid #e1e7e5; border-radius: 12px; padding: 22px; margin: 0 0 20px; }
    .sz-section-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 22px; }
    .sz-section-heading h2 { font-size: 16px; font-weight: 650; margin: 0; }
    .sz-section-heading > span { font-size: 12px; color: #687b83; white-space: nowrap; }
    .sz-events { list-style: none; padding: 0; margin: 0; }
    .sz-event { position: relative; padding: 0 0 24px 27px; margin-left: 6px; border-left: 1px solid #dce5e1; }
    .sz-event:last-child { border-left-color: transparent; padding-bottom: 0; }
    .sz-event-dot { position: absolute; left: -5px; top: 4px; width: 9px; height: 9px; background: #a4b7ae; border-radius: 50%; box-shadow: 0 0 0 4px #fff; }
    .sz-event.is-complete .sz-event-dot { width: 17px; height: 17px; left: -9px; top: 0; background: #e5f5ed; color: #14734e; font-size: 11px; display: grid; place-items: center; }
    .sz-event time { display: block; font-size: 11px; color: #72828a; font-variant-numeric: tabular-nums; margin-bottom: 5px; }
    .sz-event > strong { display: block; font-size: 13px; font-weight: 600; line-height: 1.5; color: #243e48; }
    .sz-event-note { margin-top: 5px; }
    .sz-event-note summary { cursor: pointer; font-size: 12px; color: #61777f; }
    .sz-event-note p { white-space: pre-wrap; overflow-wrap: anywhere; font-size: 13px; line-height: 1.6; color: #50636c; margin: 8px 0 0; }
    .sz-ticket { max-width: 1240px; margin: 0 auto; }
    .sz-ticket-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 24px; }
    .sz-ticket-header h1 { font-size: 24px; font-weight: 650; margin: 6px 0; }
    .sz-ticket-code { font-size: 12px; color: #72828a; overflow-wrap: anywhere; }
    .sz-ticket-status { font-size: 12px; font-weight: 600; border-radius: 20px; padding: 7px 12px; background: #eaf3ef; color: #17684c; white-space: nowrap; }
    .sz-ticket-grid { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(280px, 1fr); gap: 24px; align-items: start; }
    .sz-ticket-facts { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 24px; margin: 0 0 20px; }
    .sz-ticket-facts dt { font-size: 12px; font-weight: 400; color: #72828a; margin-bottom: 4px; }
    .sz-ticket-facts dd { font-size: 14px; font-weight: 600; margin: 0; overflow-wrap: anywhere; }
    .sz-ticket label { font-size: 13px; font-weight: 600; margin-bottom: 6px; }
    .sz-ticket form > p, .sz-ticket form > small { font-size: 13px; color: #687b83; line-height: 1.6; }
    .sz-ticket .btn { font-size: 13px; border-radius: 8px; }
    @media(max-width: 850px) { .sz-ticket-grid { grid-template-columns: minmax(0, 1fr); } .sz-ticket-header { flex-wrap: wrap; } }
    @media(max-width: 480px) { .sz-ticket-facts { grid-template-columns: 1fr; } .sz-detail .info { grid-template-columns: 1fr; } .sz-detail .info dt { border: 0; padding-bottom: 0; } .sz-timeline { padding: 18px; } }
</style>
@endpush
@endonce
