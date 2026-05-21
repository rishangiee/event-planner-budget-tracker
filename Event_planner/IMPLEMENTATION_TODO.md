# Implementation TODO - Dashboard Redesign

## Phase 1: User Dashboard (Facebook-Inspired UI)

### Step 1: Create user_dashboard_new.php
- [ ] Create 3-column layout (left sidebar 250px, center flex-1, right sidebar 300px)
- [ ] Implement Top Navigation Bar with search, icons
- [ ] Create Left Sidebar with Profile card, nav items (My Events, Calendar, Budget)
- [ ] Create Center Feed with "Create Event" box (social post style)
- [ ] Create Event cards (NOT tables) with social interactions
- [ ] Create Right Sidebar with Upcoming, Budget, Notifications
- [ ] Add Visual budget progress bars
- [ ] Add Chat/messaging panel

### Step 2: Features to Implement
- [ ] Search bar in top nav
- [ ] Notification bell icon
- [ ] Message icon with unread count
- [ ] User profile card in left sidebar
- [ ] "Create Event" expandable box in center
- [ ] Event card: title, date, description, attendees, budget, actions
- [ ] Like/Comment/Share buttons on events
- [ ] Budget progress visualization
- [ ] Chat slide-in panel

## Phase 2: Admin Dashboard (Professional Dark Theme)

### Step 1: Create admin_dashboard_new.php
- [ ] Dark professional color scheme (#1a1a2e base)
- [ ] 2-column layout (sidebar 260px, main content)
- [ ] Professional sidebar navigation
- [ ] Statistical cards grid (4 cards row)
- [ ] Data tables with search/filter
- [ ] Clean dark UI components

### Step 2: Features to Implement
- [ ] Dark theme CSS variables
- [ ] Stats: Total Events, Users, Budget, Attendees
- [ ] Events table with status badges
- [ ] Users table with role badges
- [ ] Search input with filter
- [ ] Sidebar: Dashboard, Users, Events, Budgets, Messages

## Dependencies
- Font Awesome 6.4.0 (already included)
- Tailwind CSS (already included)
- Google Fonts: Inter (already included)

## Database Considerations
- Use existing events table
- Use existing users table
- No new tables needed

## Acceptance Criteria
1. ✅ User dashboard loads with Facebook-style 3-column layout
2. ✅ User can create events from center feed box
3. ✅ Events display as social cards (not tables)
4. ✅ Budget progress visualized with progress bars
5. ✅ Admin dashboard has dark professional theme
6. ✅ Admin can view/search all users and events
7. ✅ Both dashboards are responsive
