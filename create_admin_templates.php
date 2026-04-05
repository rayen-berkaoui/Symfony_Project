<?php
/**
 * Create Admin Templates
 * Run this script to create the admin templates directory and files
 */

$projectRoot = __DIR__;
$adminDir = $projectRoot . '/templates/admin';

echo "Creating admin templates...\n\n";

// Create directory
if (!is_dir($adminDir)) {
    mkdir($adminDir, 0755, true);
    echo "✓ Created directory: $adminDir\n";
} else {
    echo "✓ Directory exists: $adminDir\n";
}

// Dashboard template
$dashboard = <<<'TWIG'
{% extends 'base.html.twig' %}

{% block title %}Admin Dashboard - Tabaany{% endblock %}

{% block stylesheets %}
<style>
    .admin-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem;
    }
    
    .admin-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 3rem 2rem;
        margin: -2rem -2rem 2rem -2rem;
        border-radius: 0 0 20px 20px;
    }
    
    .admin-header h1 {
        margin: 0 0 0.5rem 0;
        font-size: 2.5rem;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-left: 4px solid #667eea;
    }
    
    .stat-card.active { border-left-color: #10b981; }
    .stat-card.blocked { border-left-color: #ef4444; }
    .stat-card.pending { border-left-color: #f59e0b; }
    
    .stat-card h3 {
        font-size: 0.875rem;
        color: #6b7280;
        margin: 0 0 0.5rem 0;
        text-transform: uppercase;
        font-weight: 600;
    }
    
    .stat-card .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        color: #111827;
        display: block;
    }
    
    .content-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
    }
    
    .panel {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .panel h2 {
        margin: 0 0 1.5rem 0;
        font-size: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .user-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .user-item {
        display: flex;
        align-items: center;
        padding: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .user-item:last-child {
        border-bottom: none;
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        margin-right: 1rem;
        overflow: hidden;
    }
    
    .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .user-info {
        flex: 1;
    }
    
    .user-name {
        font-weight: 600;
        color: #111827;
        display: block;
    }
    
    .user-email {
        font-size: 0.875rem;
        color: #6b7280;
    }
    
    .user-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .user-badge.active {
        background: #d1fae5;
        color: #065f46;
    }
    
    .user-badge.blocked {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .user-badge.pending {
        background: #fef3c7;
        color: #92400e;
    }
    
    .role-chart {
        margin-top: 1rem;
    }
    
    .role-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .role-item:last-child {
        border-bottom: none;
    }
    
    .role-bar {
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
        flex: 1;
        margin: 0 1rem;
    }
    
    .role-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        transition: width 0.3s ease;
    }
    
    .btn-admin {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        transition: transform 0.2s;
    }
    
    .btn-admin:hover {
        transform: translateY(-2px);
        color: white;
    }
    
    @media (max-width: 1024px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
{% endblock %}

{% block body %}
<div class="admin-container">
    <div class="admin-header">
        <h1>🎛️ Admin Dashboard</h1>
        <p>Bienvenue, {{ app.user.nom }} {{ app.user.prenom }}</p>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Users</h3>
            <span class="stat-value">{{ totalUsers }}</span>
        </div>
        
        <div class="stat-card active">
            <h3>Active Users</h3>
            <span class="stat-value">{{ activeUsers }}</span>
        </div>
        
        <div class="stat-card blocked">
            <h3>Blocked Users</h3>
            <span class="stat-value">{{ blockedUsers }}</span>
        </div>
        
        <div class="stat-card pending">
            <h3>Pending Users</h3>
            <span class="stat-value">{{ pendingUsers }}</span>
        </div>
    </div>
    
    <div class="content-grid">
        <div class="panel">
            <h2>
                Recent Users
                <a href="{{ path('app_admin_users') }}" class="btn-admin">View All Users</a>
            </h2>
            <ul class="user-list">
                {% for user in recentUsers %}
                <li class="user-item">
                    <div class="user-avatar">
                        {% if user.profilePicture %}
                            <img src="{{ user.profilePicture }}" alt="{{ user.nom }}">
                        {% else %}
                            {{ user.nom|slice(0,1) }}{{ user.prenom|slice(0,1) }}
                        {% endif %}
                    </div>
                    <div class="user-info">
                        <span class="user-name">{{ user.nom }} {{ user.prenom }}</span>
                        <span class="user-email">{{ user.email }}</span>
                    </div>
                    <span class="user-badge {{ user.statut|lower }}">{{ user.statut }}</span>
                </li>
                {% endfor %}
            </ul>
        </div>
        
        <div class="panel">
            <h2>Users by Role</h2>
            <div class="role-chart">
                {% set maxCount = 0 %}
                {% for stat in roleStats %}
                    {% if stat.userCount > maxCount %}
                        {% set maxCount = stat.userCount %}
                    {% endif %}
                {% endfor %}
                
                {% for stat in roleStats %}
                <div class="role-item">
                    <span style="font-weight: 600;">{{ stat.nom }}</span>
                    <div class="role-bar">
                        <div class="role-bar-fill" style="width: {{ maxCount > 0 ? (stat.userCount / maxCount * 100) : 0 }}%"></div>
                    </div>
                    <span style="font-weight: 600; color: #667eea;">{{ stat.userCount }}</span>
                </div>
                {% endfor %}
            </div>
        </div>
    </div>
</div>
{% endblock %}
TWIG;

file_put_contents($adminDir . '/dashboard.html.twig', $dashboard);
echo "✓ Created: dashboard.html.twig\n";

// Users template
$users = <<<'TWIG'
{% extends 'base.html.twig' %}

{% block title %}Manage Users - Admin{% endblock %}

{% block body %}
<div style="max-width: 1400px; margin: 0 auto; padding: 2rem;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 3rem 2rem; margin: -2rem -2rem 2rem -2rem; border-radius: 0 0 20px 20px;">
        <a href="{{ path('app_admin_dashboard') }}" style="color: white; text-decoration: none; display: inline-block; margin-bottom: 1rem;">
            ← Back to Dashboard
        </a>
        <h1 style="margin: 0 0 0.5rem 0; font-size: 2.5rem;">👥 Manage Users</h1>
        <p style="margin: 0;">Search, filter, and manage all application users</p>
    </div>
    
    <div style="background: white; color: #111; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 1.5rem;">
        <form method="get" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem;">
            <input type="text" name="search" value="{{ search }}" placeholder="Search by name or email..." style="padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px;">
            
            <select name="status" style="padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px;">
                <option value="">All Statuses</option>
                <option value="ACTIF" {{ status == 'ACTIF' ? 'selected' : '' }}>Active</option>
                <option value="BLOQUE" {{ status == 'BLOQUE' ? 'selected' : '' }}>Blocked</option>
                <option value="EN_ATTENTE" {{ status == 'EN_ATTENTE' ? 'selected' : '' }}>Pending</option>
            </select>
            
            <select name="role" style="padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px;">
                <option value="">All Roles</option>
                <option value="ADMIN" {{ role == 'ADMIN' ? 'selected' : '' }}>Admin</option>
                <option value="TOURISTE" {{ role == 'TOURISTE' ? 'selected' : '' }}>Tourist</option>
            </select>
            
            <button type="submit" style="padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Search</button>
        </form>
    </div>
    
    <div style="background: white; color: #111; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <table style="width: 100%; border-collapse: collapse;">
            <thead style="background: #f9fafb;">
                <tr>
                    <th style="padding: 1rem; text-align: left; font-weight: 600; border-bottom: 2px solid #e5e7eb;">User</th>
                    <th style="padding: 1rem; text-align: left; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Email</th>
                    <th style="padding: 1rem; text-align: left; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Role</th>
                    <th style="padding: 1rem; text-align: left; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Status</th>
                    <th style="padding: 1rem; text-align: left; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Joined</th>
                    <th style="padding: 1rem; text-align: left; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Actions</th>
                </tr>
            </thead>
            <tbody>
                {% for user in users %}
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 1rem;">
                        <strong>{{ user.nom }} {{ user.prenom }}</strong>
                    </td>
                    <td style="padding: 1rem;">{{ user.email }}</td>
                    <td style="padding: 1rem;">{{ user.role.nom }}</td>
                    <td style="padding: 1rem;">
                        <span style="padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: {% if user.statut == 'ACTIF' %}#d1fae5; color: #065f46{% elseif user.statut == 'BLOQUE' %}#fee2e2; color: #991b1b{% else %}#fef3c7; color: #92400e{% endif %};">
                            {{ user.statut }}
                        </span>
                    </td>
                    <td style="padding: 1rem;">{{ user.dateCreation|date('d/m/Y') }}</td>
                    <td style="padding: 1rem;">
                        <a href="{{ path('app_admin_user_view', {id: user.id}) }}" style="color: #667eea; text-decoration: none;">View</a>
                    </td>
                </tr>
                {% else %}
                <tr>
                    <td colspan="6" style="text-align: center; padding: 3rem; color: #6b7280;">
                        No users found
                    </td>
                </tr>
                {% endfor %}
            </tbody>
        </table>
    </div>
</div>
{% endblock %}
TWIG;

file_put_contents($adminDir . '/users.html.twig', $users);
echo "✓ Created: users.html.twig\n";

// User detail template
$userDetail = <<<'TWIG'
{% extends 'base.html.twig' %}

{% block title %}{{ user.nom }} {{ user.prenom }} - User Details{% endblock %}

{% block body %}
<div style="max-width: 1200px; margin: 0 auto; padding: 2rem;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 3rem 2rem; margin: -2rem -2rem 2rem -2rem; border-radius: 0 0 20px 20px;">
        <a href="{{ path('app_admin_users') }}" style="color: white; text-decoration: none; display: inline-block; margin-bottom: 1rem;">
            ← Back to Users
        </a>
        <h1 style="margin: 0;">User Details</h1>
    </div>
    
    <div style="display: grid; grid-template-columns: 300px 1fr; gap: 2rem;">
        <div style="background: white; color: #111; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; height: fit-content;">
            <div style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; font-weight: 700; margin: 0 auto 1.5rem auto;">
                {{ user.nom|slice(0,1) }}{{ user.prenom|slice(0,1) }}
            </div>
            <h2 style="margin: 0;">{{ user.nom }} {{ user.prenom }}</h2>
            <p style="color: #6b7280; margin: 0.5rem 0 1rem 0;">{{ user.role.nom }}</p>
            <span style="padding: 0.5rem 1rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; display: inline-block; background: {% if user.statut == 'ACTIF' %}#d1fae5; color: #065f46{% elseif user.statut == 'BLOQUE' %}#fee2e2; color: #991b1b{% else %}#fef3c7; color: #92400e{% endif %};">
                {{ user.statut }}
            </span>
        </div>
        
        <div style="background: white; color: #111; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h2 style="margin: 0 0 1.5rem 0; font-size: 1.5rem;">Personal Information</h2>
            
            <div style="display: grid; grid-template-columns: 150px 1fr; padding: 1rem 0; border-bottom: 1px solid #e5e7eb;">
                <div style="font-weight: 600; color: #6b7280;">ID</div>
                <div>{{ user.id }}</div>
            </div>
            
            <div style="display: grid; grid-template-columns: 150px 1fr; padding: 1rem 0; border-bottom: 1px solid #e5e7eb;">
                <div style="font-weight: 600; color: #6b7280;">First Name</div>
                <div>{{ user.prenom }}</div>
            </div>
            
            <div style="display: grid; grid-template-columns: 150px 1fr; padding: 1rem 0; border-bottom: 1px solid #e5e7eb;">
                <div style="font-weight: 600; color: #6b7280;">Last Name</div>
                <div>{{ user.nom }}</div>
            </div>
            
            <div style="display: grid; grid-template-columns: 150px 1fr; padding: 1rem 0; border-bottom: 1px solid #e5e7eb;">
                <div style="font-weight: 600; color: #6b7280;">Email</div>
                <div>{{ user.email }}</div>
            </div>
            
            <div style="display: grid; grid-template-columns: 150px 1fr; padding: 1rem 0; border-bottom: 1px solid #e5e7eb;">
                <div style="font-weight: 600; color: #6b7280;">Phone</div>
                <div>{{ user.numTel ?? 'N/A' }}</div>
            </div>
            
            <div style="display: grid; grid-template-columns: 150px 1fr; padding: 1rem 0; border-bottom: 1px solid #e5e7eb;">
                <div style="font-weight: 600; color: #6b7280;">Language</div>
                <div>{{ user.language ?? 'fr' }}</div>
            </div>
            
            <div style="display: grid; grid-template-columns: 150px 1fr; padding: 1rem 0; border-bottom: 1px solid #e5e7eb;">
                <div style="font-weight: 600; color: #6b7280;">Loyalty Points</div>
                <div>{{ user.loyaltyPoints ?? 0 }}</div>
            </div>
            
            <div style="display: grid; grid-template-columns: 150px 1fr; padding: 1rem 0;">
                <div style="font-weight: 600; color: #6b7280;">Joined</div>
                <div>{{ user.dateCreation|date('F d, Y at H:i') }}</div>
            </div>
        </div>
    </div>
</div>
{% endblock %}
TWIG;

file_put_contents($adminDir . '/user_detail.html.twig', $userDetail);
echo "✓ Created: user_detail.html.twig\n";

echo "\n✅ All admin templates created successfully!\n";
echo "\nYou can now:\n";
echo "1. Login as admin (username: admin, password: admin)\n";
echo "2. Access the admin dashboard at /admin\n";
echo "3. Manage users at /admin/users\n";
