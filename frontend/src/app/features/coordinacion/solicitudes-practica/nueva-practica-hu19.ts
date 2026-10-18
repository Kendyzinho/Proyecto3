import { Component } from '@angular/core';
import { MatIconModule } from '@angular/material/icon';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-nueva-practica-hu19',
  imports: [MatIconModule, RouterLink],
  template: `
    <main class="placeholder-page">
      <a class="back-link" routerLink="/coordinacion/solicitudes"><mat-icon>arrow_back</mat-icon>Volver a solicitudes</a>
      <section class="placeholder-card">
        <span class="placeholder-icon"><mat-icon>construction</mat-icon></span>
        <p class="eyebrow">REGISTRO EXCEPCIONAL · HU-19</p>
        <h1>Nueva práctica</h1>
        <p>El registro excepcional se implementará en la historia HU-19. Esta pantalla confirma el acceso desde la bandeja de solicitudes.</p>
        <a class="primary-link" routerLink="/coordinacion/solicitudes">Ir a solicitudes por revisar</a>
      </section>
    </main>
  `,
  styles: [`
    :host { display: block; }
    .placeholder-page { max-width: 1100px; margin: 0 auto; padding: 38px 46px; color: #26354b; }
    .back-link { display: inline-flex; align-items: center; gap: 5px; margin-bottom: 26px; color: #526b94; font-size: 13px; text-decoration: none; }
    .back-link mat-icon { width: 18px; height: 18px; font-size: 18px; }
    .placeholder-card { display: grid; justify-items: center; padding: 70px 28px; border: 1px solid #e1e7ef; border-radius: 16px; background: white; text-align: center; box-shadow: 0 2px 4px #2532470c; }
    .placeholder-icon { width: 56px; height: 56px; display: grid; place-items: center; border-radius: 50%; color: #355085; background: #e8f0f9; }
    .placeholder-icon mat-icon { width: 28px; height: 28px; font-size: 28px; }
    .eyebrow { margin: 18px 0 6px; color: #ad6218; font-size: 11px; font-weight: 750; letter-spacing: .08em; }
    h1 { margin: 0; color: #202d42; font-size: 30px; }
    .placeholder-card > p:not(.eyebrow) { max-width: 560px; color: #718096; font-size: 14px; line-height: 1.6; }
    .primary-link { margin-top: 12px; padding: 12px 19px; border-radius: 24px; color: white; background: #355085; font-size: 13px; text-decoration: none; }
    .primary-link:hover { background: #29416c; }
    @media (max-width: 600px) { .placeholder-page { padding: 26px 16px; }.placeholder-card { padding: 48px 20px; } }
  `],
})
export class NuevaPracticaHu19 {}
