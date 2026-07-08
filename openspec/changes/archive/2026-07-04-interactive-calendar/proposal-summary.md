## Proposal Created

**Change**: interactive-calendar
**Location**: `openspec/changes/interactive-calendar/proposal.md` (openspec/hybrid) | Engram `sdd/interactive-calendar/proposal` (engram)

### Summary
- **Intent**: Implement core availability algorithm and public calendar UI for customers to view real-time time slots per service/employee/date
- **Scope**: 6 deliverables in (availability service, Livewire component, routes, index, tests, tenant isolation), 5 items deferred (booking creation, holds, payment, slot selection, mobile optimization)
- **Approach**: Livewire 3 + Alpine.js with AvailabilityService for slot generation and conflict filtering
- **Risk Level**: Medium

### Next Step
Ready for specs (sdd-spec) or design (sdd-design).

### Proposal Questions for Orchestrator Review
1. Slot step interval: service-duration vs fixed (e.g., 15min)?
2. Buffer time between bookings: configurable or none?
3. Past-time filtering: filter out or show as unavailable?
4. Calendar UI scope: display-only or with non-functional "Select" button?
5. Employee selection: show all employees at once or require selection first?