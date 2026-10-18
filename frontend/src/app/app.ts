import { Component, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { Auth } from './core/services/auth';
import { AplicacionShell } from './shared/aplicacion-shell/aplicacion-shell';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, AplicacionShell],
  templateUrl: './app.html',
  styleUrl: './app.scss'
})
export class App {
  protected readonly auth = inject(Auth);
}
